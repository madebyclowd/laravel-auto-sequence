<?php

namespace MadeByClowd\AutoSequence;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MadeByClowd\AutoSequence\Events\SequenceExhausted;
use MadeByClowd\AutoSequence\Events\SequenceGenerated;
use MadeByClowd\AutoSequence\Events\SequenceRecycled;
use MadeByClowd\AutoSequence\Events\SequenceResetPerformed;
use MadeByClowd\AutoSequence\Exceptions\SequenceLockException;
use MadeByClowd\AutoSequence\Models\Sequence;

class SequenceManager
{
    /**
     * Generate the next sequence number.
     *
     * @param  string  $module  Domain or entity area (e.g., 'invoice')
     * @param  string  $typeCode  Sub-type code (e.g., 'INV')
     * @param  string|null  $period  Reset period identifier (e.g., '202606' or resolved dynamically)
     * @param  mixed  $formatTemplate  The template format string or Closure
     * @param  int  $padLength  Zero padding length (default 5)
     * @param  string  $scope  Multi-tenancy or organizational scope (default 'default')
     * @param  Model|null  $model  Optional Eloquent model context for dynamic attributes
     * @param  string|null  $connection  Optional database connection override
     * @param  int  $startValue  Starting number value (default 1)
     * @param  int  $step  Increment step size (default 1)
     * @param  bool  $continuous  Whether to enable continuous sequence recycling
     * @param  int|null  $maxValue  Optional maximum limit
     * @param  int|null  $exhaustionThreshold  Percentage of $maxValue at which SequenceExhausted fires (default: config('auto-sequence.exhaustion_threshold'))
     */
    public function generate(
        string $module,
        string $typeCode,
        ?string $period = null,
        mixed $formatTemplate = null,
        int $padLength = 5,
        string $scope = 'default',
        ?Model $model = null,
        ?string $connection = null,
        int $startValue = 1,
        int $step = 1,
        bool $continuous = false,
        ?int $maxValue = null,
        ?int $exhaustionThreshold = null
    ): string {
        if ($padLength < 1) {
            throw new Exceptions\AutoSequenceException("Sequence config 'pad_length' must be a positive integer greater than 0.");
        }
        if ($startValue < 0) {
            throw new Exceptions\AutoSequenceException("Sequence config 'start_value' must be greater than or equal to 0.");
        }
        if ($step < 1) {
            throw new Exceptions\AutoSequenceException("Sequence config 'step' must be a positive integer greater than 0.");
        }

        $period = $period ?? now()->format('Ym');
        $resolvedConnection = $this->resolveConnectionName($connection);

        // Resolve format template if it's a closure
        if ($formatTemplate instanceof Closure) {
            $formatTemplate = $formatTemplate($model);
        }

        $thresholdPercent = $exhaustionThreshold ?? (int) config('auto-sequence.exhaustion_threshold', 90);

        // 1. Continuous Sequence (Gap Recycling) Check
        if ($continuous) {
            $recycledNumber = $this->claimRecycledNumber($resolvedConnection, $module, $typeCode, $period, $scope);
            if ($recycledNumber !== null) {
                if ($maxValue !== null && $recycledNumber > $maxValue) {
                    throw new Exceptions\AutoSequenceException("Sequence [{$module}][{$typeCode}] has exceeded its maximum limit of {$maxValue}.");
                }

                $formattedNumber = $this->formatNumber(
                    $module,
                    $typeCode,
                    $period,
                    $scope,
                    $recycledNumber,
                    $formatTemplate,
                    $padLength,
                    $model
                );

                SequenceGenerated::dispatch($module, $typeCode, $period, $scope, $formattedNumber, $model);

                return $formattedNumber;
            }
        }

        // 2. Standard generation
        if (config('auto-sequence.pre_allocation.enabled', false) && ! $continuous) {
            if (config('auto-sequence.transaction_mode', 'gapless') === 'gapless') {
                throw new Exceptions\AutoSequenceException(
                    "High-performance pre-allocation cannot be used with 'gapless' transaction mode. Please set transaction_mode to 'gap_tolerant' or disable pre-allocation."
                );
            }
            $nextNumber = $this->generateViaPreAllocation(
                $resolvedConnection,
                $module,
                $typeCode,
                $period,
                $scope,
                $formatTemplate,
                $startValue,
                $step,
                $maxValue,
                $thresholdPercent
            );
        } else {
            $nextNumber = $this->generateViaLocking(
                $resolvedConnection,
                $module,
                $typeCode,
                $period,
                $scope,
                $formatTemplate,
                $startValue,
                $step,
                $maxValue,
                $thresholdPercent
            );
        }

        if ($maxValue !== null && $nextNumber['number'] > $maxValue) {
            throw new Exceptions\AutoSequenceException("Sequence [{$module}][{$typeCode}] has exceeded its maximum limit of {$maxValue}.");
        }

        $formattedNumber = $this->formatNumber(
            $module,
            $typeCode,
            $period,
            $scope,
            $nextNumber['number'],
            $nextNumber['template'],
            $padLength,
            $model
        );

        SequenceGenerated::dispatch($module, $typeCode, $period, $scope, $formattedNumber, $model);

        if (! empty($nextNumber['just_exhausted']) && $maxValue !== null) {
            SequenceExhausted::dispatch($module, $typeCode, $period, $scope, $nextNumber['number'], $maxValue, $model);
        }

        return $formattedNumber;
    }

    /**
     * Get the current sequence counter value without incrementing.
     */
    public function getCurrent(string $module, string $typeCode, ?string $period = null, string $scope = 'default'): int
    {
        $period = $period ?? now()->format('Ym');
        $connectionName = $this->resolveConnectionName();

        $sequence = Sequence::on($connectionName)
            ->where('module', $module)
            ->where('type_code', $typeCode)
            ->where('period', $period)
            ->where('scope', $scope)
            ->first();

        return $sequence ? $sequence->current_number : 0;
    }

    /**
     * Reset or set the sequence counter to a specific number.
     */
    public function reset(
        string $module,
        string $typeCode,
        ?string $period = null,
        string $scope = 'default',
        int $resetTo = 0
    ): void {
        $period = $period ?? now()->format('Ym');
        $connectionName = $this->resolveConnectionName();
        $userId = Auth::id();

        // Clear pre-allocation cache if active
        if (config('auto-sequence.pre_allocation.enabled', false)) {
            $cacheKey = $this->getPreAllocationCacheKey($module, $typeCode, $period, $scope);
            $cacheStore = config('auto-sequence.pre_allocation.store');
            Cache::store($cacheStore)->forget($cacheKey);
        }

        $auditEnabled = config('auto-sequence.audit.enabled', false);
        $createdByColumn = config('auto-sequence.audit.created_by_column', 'created_by');
        $updatedByColumn = config('auto-sequence.audit.updated_by_column', 'updated_by');

        $attributes = [
            'current_number' => $resetTo,
            'exhausted_notified_at' => null,
        ];

        if ($auditEnabled && $userId) {
            $attributes[$updatedByColumn] = $userId;
        }

        $matchThese = [
            'module' => $module,
            'type_code' => $typeCode,
            'period' => $period,
            'scope' => $scope,
        ];

        $sequence = Sequence::on($connectionName)
            ->where('module', $module)
            ->where('type_code', $typeCode)
            ->where('period', $period)
            ->where('scope', $scope)
            ->first();

        if ($sequence) {
            $sequence->forceFill(array_merge($attributes, $auditEnabled && $userId ? [$updatedByColumn => $userId] : []))->save();
        } else {
            $attributes = array_merge($matchThese, $attributes, $auditEnabled && $userId ? [$createdByColumn => $userId, $updatedByColumn => $userId] : []);
            $sequence = new Sequence;
            $sequence->forceFill($attributes);
            $sequence->setConnection($connectionName);
            $sequence->save();
        }

        SequenceResetPerformed::dispatch($module, $typeCode, $period, $scope, $resetTo);
    }

    /**
     * Generate next number using transactional concurrency locks.
     */
    protected function generateViaLocking(
        ?string $connectionName,
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate,
        int $startValue = 1,
        int $step = 1,
        ?int $maxValue = null,
        ?int $exhaustionThresholdPercent = null
    ): array {
        $lockingDriver = config('auto-sequence.locking.driver', 'database');
        $timeoutSeconds = config('auto-sequence.locking.timeout', 5);

        if ($lockingDriver === 'cache') {
            $lockStore = config('auto-sequence.locking.cache_store');
            $store = Cache::store($lockStore);
            if (! $store->getStore() instanceof LockProvider) {
                throw new Exceptions\AutoSequenceException(
                    "The cache store '".($lockStore ?: config('cache.default'))."' does not support atomic locks. Please configure a compatible store (e.g. redis, database, memcached)."
                );
            }

            $lockKey = "sequence_lock:{$module}:{$typeCode}:{$period}:{$scope}";
            $lock = $store->lock($lockKey, $timeoutSeconds);

            try {
                try {
                    $lock->block($timeoutSeconds);
                } catch (LockTimeoutException) {
                    throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode}", $timeoutSeconds);
                }

                return $this->incrementDatabaseSequence($connectionName, $module, $typeCode, $period, $scope, $formatTemplate, $step, $startValue, $step, $maxValue, $exhaustionThresholdPercent);
            } finally {
                $lock->release();
            }
        }

        // Database locking (Pessimistic)
        $connection = DB::connection($connectionName);

        try {
            return $connection->transaction(function () use ($connection, $connectionName, $module, $typeCode, $period, $scope, $formatTemplate, $step, $startValue, $timeoutSeconds, $maxValue, $exhaustionThresholdPercent) {
                $this->setDatabaseLockTimeout($connection, $timeoutSeconds);

                return $this->incrementDatabaseSequence($connectionName, $module, $typeCode, $period, $scope, $formatTemplate, $step, $startValue, $step, $maxValue, $exhaustionThresholdPercent);
            });
        } catch (\Throwable $e) {
            throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode}", $timeoutSeconds, $e);
        }
    }

    /**
     * Generate next number using Hi/Lo pre-allocation caching.
     */
    protected function generateViaPreAllocation(
        ?string $connectionName,
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate,
        int $startValue = 1,
        int $step = 1,
        ?int $maxValue = null,
        ?int $exhaustionThresholdPercent = null
    ): array {
        $cacheKey = $this->getPreAllocationCacheKey($module, $typeCode, $period, $scope);
        $blockSize = (int) config('auto-sequence.pre_allocation.block_size', 50);
        $timeoutSeconds = config('auto-sequence.locking.timeout', 5);
        $cacheStore = config('auto-sequence.pre_allocation.store');
        $cache = Cache::store($cacheStore);

        // Fetch current block from cache
        $cached = $cache->get($cacheKey);

        if ($cached && ($cached['current'] + $step) <= $cached['max']) {
            $newCurrent = $cached['current'] + $step;
            $cached['current'] = $newCurrent;
            $cache->put($cacheKey, $cached, 86400); // Cache for 24h

            return [
                'number' => $newCurrent,
                'template' => $cached['template'],
                'just_exhausted' => false,
            ];
        }

        // Cache empty or exhausted, fetch next block from database
        $lockStore = config('auto-sequence.locking.cache_store');
        $store = Cache::store($lockStore);
        if (! $store->getStore() instanceof LockProvider) {
            throw new Exceptions\AutoSequenceException(
                "The cache store '".($lockStore ?: config('cache.default'))."' does not support atomic locks. Please configure a compatible store (e.g. redis, database, memcached)."
            );
        }

        $lockKey = "sequence_lock:pre_allocation:{$module}:{$typeCode}:{$period}:{$scope}";
        $lock = $store->lock($lockKey, $timeoutSeconds);

        try {
            try {
                $lock->block($timeoutSeconds);
            } catch (LockTimeoutException) {
                throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode} (pre-allocation)", $timeoutSeconds);
            }

            // Double check cache after acquiring lock
            $cached = $cache->get($cacheKey);
            if ($cached && ($cached['current'] + $step) <= $cached['max']) {
                $newCurrent = $cached['current'] + $step;
                $cached['current'] = $newCurrent;
                $cache->put($cacheKey, $cached, 86400);

                return [
                    'number' => $newCurrent,
                    'template' => $cached['template'],
                    'just_exhausted' => false,
                ];
            }

            // Increment database by block size * step
            $incrementSize = $blockSize * $step;
            $dbResult = DB::connection($connectionName)->transaction(function () use ($connectionName, $module, $typeCode, $period, $scope, $formatTemplate, $incrementSize, $startValue, $step, $maxValue, $exhaustionThresholdPercent) {
                return $this->incrementDatabaseSequence($connectionName, $module, $typeCode, $period, $scope, $formatTemplate, $incrementSize, $startValue, $step, $maxValue, $exhaustionThresholdPercent);
            });

            $max = $dbResult['number'];
            $current = $max - $incrementSize + $step;

            $cache->put($cacheKey, [
                'current' => $current,
                'max' => $max,
                'template' => $dbResult['template'],
            ], 86400);

            return [
                'number' => $current,
                'template' => $dbResult['template'],
                'just_exhausted' => $dbResult['just_exhausted'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Atomically fetch and increment the database sequence record.
     */
    protected function incrementDatabaseSequence(
        ?string $connectionName,
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate,
        int $incrementBy,
        int $startValue = 1,
        int $step = 1,
        ?int $maxValue = null,
        ?int $exhaustionThresholdPercent = null
    ): array {
        $userId = Auth::id();
        $auditEnabled = config('auto-sequence.audit.enabled', false);
        $createdByColumn = config('auto-sequence.audit.created_by_column', 'created_by');
        $updatedByColumn = config('auto-sequence.audit.updated_by_column', 'updated_by');

        $sequence = Sequence::on($connectionName)
            ->where('module', $module)
            ->where('type_code', $typeCode)
            ->where('period', $period)
            ->where('scope', $scope)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $attributes = [
                'module' => $module,
                'type_code' => $typeCode,
                'period' => $period,
                'scope' => $scope,
                'current_number' => $startValue + $incrementBy - $step,
                'format_template' => $formatTemplate,
            ];

            if ($auditEnabled && $userId) {
                $attributes[$createdByColumn] = $userId;
                $attributes[$updatedByColumn] = $userId;
            }

            $sequence = new Sequence;
            $sequence->forceFill($attributes);
            $sequence->setConnection($connectionName);
            $justExhausted = $this->checkExhaustionThreshold($sequence, $maxValue, $exhaustionThresholdPercent);
            $sequence->save();
        } else {
            // Update template if provided and different
            if ($formatTemplate && $sequence->format_template !== $formatTemplate) {
                $sequence->format_template = $formatTemplate;
            }

            $sequence->current_number += $incrementBy;

            if ($auditEnabled && $userId) {
                $sequence->setAttribute($updatedByColumn, $userId);
            }

            $justExhausted = $this->checkExhaustionThreshold($sequence, $maxValue, $exhaustionThresholdPercent);
            $sequence->save();
        }

        return [
            'number' => $sequence->current_number,
            'template' => $sequence->format_template,
            'just_exhausted' => $justExhausted,
        ];
    }

    /**
     * Check whether this sequence just crossed its exhaustion threshold,
     * marking it as notified (once per partition) if so.
     */
    protected function checkExhaustionThreshold(Sequence $sequence, ?int $maxValue, ?int $exhaustionThresholdPercent): bool
    {
        if ($maxValue === null || $exhaustionThresholdPercent === null || $sequence->exhausted_notified_at !== null) {
            return false;
        }

        $thresholdValue = ($maxValue * $exhaustionThresholdPercent) / 100;

        if ($sequence->current_number < $thresholdValue) {
            return false;
        }

        $sequence->exhausted_notified_at = now();

        return true;
    }

    /**
     * Format the sequence number based on template.
     */
    protected function formatNumber(
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        int $number,
        ?string $template,
        int $padLength,
        ?Model $model
    ): string {
        $paddedNumber = str_pad((string) $number, $padLength, '0', STR_PAD_LEFT);

        if (! $template) {
            // Default fallback pattern: TYPE-PERIOD-PADDEDNUMBER
            return "{$typeCode}-{$period}-{$paddedNumber}";
        }

        // Replace custom date codes, padded numbers, and properties
        $replacements = [
            '{module}' => strtoupper($module),
            '{type_code}' => strtoupper($typeCode),
            '{type-code}' => strtoupper($typeCode),
            '{typeCode}' => strtoupper($typeCode),
            '{period}' => $period,
            '{scope}' => strtoupper($scope),
            '{number}' => $number,
            '{seq}' => $number,
            '{padded_number}' => $paddedNumber,
        ];

        // Format dates dynamically
        if ($model) {
            $createdAt = $model->created_at ?? now();
        } else {
            $createdAt = now();
        }

        $dateReplacements = [
            '{YYYY}' => $createdAt->format('Y'),
            '{YY}' => $createdAt->format('y'),
            '{MM}' => $createdAt->format('m'),
            '{M}' => $createdAt->format('n'),
            '{DD}' => $createdAt->format('d'),
            '{D}' => $createdAt->format('j'),
            '{HH}' => $createdAt->format('H'),
            '{mm}' => $createdAt->format('i'),
            '{ss}' => $createdAt->format('s'),
        ];

        $replacements = array_merge($replacements, $dateReplacements);

        $result = strtr($template, $replacements);

        // Replace custom date formats: {date:FORMAT}
        $result = preg_replace_callback('/\{date:([^{}]+)\}/', function ($matches) use ($createdAt) {
            return $createdAt->format($matches[1]);
        }, $result);

        // Replace dynamic length padded number: {seq:X} or {number:X}
        $result = preg_replace_callback('/\{(seq|number):(\d+)\}/', function ($matches) use ($number) {
            return str_pad((string) $number, (int) $matches[2], '0', STR_PAD_LEFT);
        }, $result);

        // Replace model attributes: {attribute:field} or {field:field} (supports dot notation)
        if ($model) {
            $result = preg_replace_callback('/\{(attribute|field):([a-zA-Z0-9_\.]+)\}/', function ($matches) use ($model) {
                return data_get($model, $matches[2]) ?? '';
            }, $result);
        }

        // Replace random strings: {rand:X} or {random:X}
        $result = preg_replace_callback('/\{(rand|random):(\d+)\}/', function ($matches) {
            return Str::random((int) $matches[2]);
        }, $result);

        return $result;
    }

    /**
     * Get pre-allocation cache key.
     */
    protected function getPreAllocationCacheKey(string $module, string $typeCode, string $period, string $scope): string
    {
        return "auto-sequence_pool:{$module}:{$typeCode}:{$period}:{$scope}";
    }

    /**
     * Resolve the database connection name based on transaction mode and overrides.
     */
    public function resolveConnectionName(?string $connectionOverride = null): ?string
    {
        $connectionName = $connectionOverride ?? config('auto-sequence.connection');

        if (config('auto-sequence.transaction_mode', 'gapless') === 'gap_tolerant') {
            $baseConnection = $connectionName ?? config('database.default');
            $baseConfig = config("database.connections.{$baseConnection}");

            // Fallback for SQLite in-memory database
            if (isset($baseConfig['driver']) && $baseConfig['driver'] === 'sqlite' && ($baseConfig['database'] === ':memory:' || $baseConfig['database'] === '')) {
                return $connectionName;
            }

            $isolatedConnectionName = "auto-sequence_isolated_{$baseConnection}";

            if (! config()->has("database.connections.{$isolatedConnectionName}")) {
                if ($baseConfig) {
                    config(["database.connections.{$isolatedConnectionName}" => $baseConfig]);
                }
            }

            return $isolatedConnectionName;
        }

        return $connectionName;
    }

    /**
     * Claim the oldest recycled number for a sequence partition.
     */
    protected function claimRecycledNumber(
        ?string $connectionName,
        string $module,
        string $typeCode,
        string $period,
        string $scope
    ): ?int {
        $recycledTable = config('auto-sequence.recycled_table', 'sequence_recycled');

        return DB::connection($connectionName)->transaction(function () use ($connectionName, $recycledTable, $module, $typeCode, $period, $scope) {
            $record = DB::connection($connectionName)
                ->table($recycledTable)
                ->where('module', $module)
                ->where('type_code', $typeCode)
                ->where('period', $period)
                ->where('scope', $scope)
                ->orderBy('number', 'asc')
                ->lockForUpdate()
                ->first();

            if ($record) {
                DB::connection($connectionName)
                    ->table($recycledTable)
                    ->where('id', $record->id)
                    ->delete();

                return (int) $record->number;
            }

            return null;
        });
    }

    /**
     * Recycle a sequence number (inserts it back into sequence_recycled table).
     */
    public function recycle(
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        int $number,
        ?string $connection = null
    ): void {
        $resolvedConnection = $this->resolveConnectionName($connection);
        $recycledTable = config('auto-sequence.recycled_table', 'sequence_recycled');

        // Prevent duplicates in the recycled queue
        $exists = DB::connection($resolvedConnection)
            ->table($recycledTable)
            ->where('module', $module)
            ->where('type_code', $typeCode)
            ->where('period', $period)
            ->where('scope', $scope)
            ->where('number', $number)
            ->exists();

        if (! $exists) {
            DB::connection($resolvedConnection)
                ->table($recycledTable)
                ->insert([
                    'module' => $module,
                    'type_code' => $typeCode,
                    'period' => $period,
                    'scope' => $scope,
                    'number' => $number,
                    'created_at' => now(),
                ]);

            SequenceRecycled::dispatch($module, $typeCode, $period, $scope, $number);
        }
    }

    /**
     * Set the database connection session/local lock wait timeout.
     */
    protected function setDatabaseLockTimeout(Connection $connection, int $timeoutSeconds): void
    {
        $driver = $connection->getDriverName();

        try {
            match ($driver) {
                'mysql' => $connection->statement("SET SESSION innodb_lock_wait_timeout = {$timeoutSeconds}"),
                'pgsql' => $connection->statement('SET LOCAL lock_timeout = '.($timeoutSeconds * 1000)),
                'sqlsrv' => $connection->statement('SET LOCK_TIMEOUT '.($timeoutSeconds * 1000)),
                'sqlite' => $connection->statement('PRAGMA busy_timeout = '.($timeoutSeconds * 1000)),
                default => null,
            };
        } catch (\Throwable $e) {
            // Silence exceptions to avoid failing if user lacks permissions or driver has custom configuration
        }
    }
}
