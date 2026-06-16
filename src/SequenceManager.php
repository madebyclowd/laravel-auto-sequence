<?php

namespace MadeByClowd\Sequenceable;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MadeByClowd\Sequenceable\Exceptions\SequenceLockException;
use MadeByClowd\Sequenceable\Models\Sequence;

class SequenceManager
{
    /**
     * Generate the next sequence number.
     *
     * @param  string  $module  Domain or entity area (e.g., 'invoice')
     * @param  string  $typeCode  Sub-type code (e.g., 'INV')
     * @param  string|null  $period  Reset period identifier (e.g., '202606' or resolved dynamically)
     * @param  string|null  $formatTemplate  The template format to use
     * @param  int  $padLength  Zero padding length (default 5)
     * @param  string  $scope  Multi-tenancy or organizational scope (default 'default')
     * @param  Model|null  $model  Optional Eloquent model context for dynamic attributes
     */
    public function generate(
        string $module,
        string $typeCode,
        ?string $period = null,
        ?string $formatTemplate = null,
        int $padLength = 5,
        string $scope = 'default',
        ?Model $model = null
    ): string {
        $period = $period ?? now()->format('Ym');

        if (config('sequenceable.pre_allocation.enabled', false)) {
            $nextNumber = $this->generateViaPreAllocation($module, $typeCode, $period, $scope, $formatTemplate);
        } else {
            $nextNumber = $this->generateViaLocking($module, $typeCode, $period, $scope, $formatTemplate);
        }

        return $this->formatNumber(
            $module,
            $typeCode,
            $period,
            $scope,
            $nextNumber['number'],
            $nextNumber['template'],
            $padLength,
            $model
        );
    }

    /**
     * Get the current sequence counter value without incrementing.
     */
    public function getCurrent(string $module, string $typeCode, ?string $period = null, string $scope = 'default'): int
    {
        $period = $period ?? now()->format('Ym');
        $connectionName = config('sequenceable.connection');

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
        $connectionName = config('sequenceable.connection');
        $userId = Auth::id();

        // Clear pre-allocation cache if active
        if (config('sequenceable.pre_allocation.enabled', false)) {
            $cacheKey = $this->getPreAllocationCacheKey($module, $typeCode, $period, $scope);
            Cache::forget($cacheKey);
        }

        $auditEnabled = config('sequenceable.audit.enabled', false);
        $createdByColumn = config('sequenceable.audit.created_by_column', 'created_by');
        $updatedByColumn = config('sequenceable.audit.updated_by_column', 'updated_by');

        $attributes = [
            'current_number' => $resetTo,
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

        Sequence::on($connectionName)->updateOrCreate(
            $matchThese,
            array_merge($matchThese, $attributes, $auditEnabled && $userId ? [$createdByColumn => $userId] : [])
        );
    }

    /**
     * Generate next number using transactional concurrency locks.
     */
    protected function generateViaLocking(
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate
    ): array {
        $lockingDriver = config('sequenceable.locking.driver', 'database');
        $timeoutSeconds = config('sequenceable.locking.timeout', 5);

        if ($lockingDriver === 'cache') {
            $lockKey = "sequence_lock:{$module}:{$typeCode}:{$period}:{$scope}";
            $lockStore = config('sequenceable.locking.cache_store');
            $lock = Cache::store($lockStore)->lock($lockKey, $timeoutSeconds);

            try {
                if (!$lock->block($timeoutSeconds)) {
                    throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode}", $timeoutSeconds);
                }
                return $this->incrementDatabaseSequence($module, $typeCode, $period, $scope, $formatTemplate, 1);
            } finally {
                $lock->release();
            }
        }

        // Database locking (Pessimistic) with retry loop
        $connectionName = config('sequenceable.connection');
        $retryIntervalMs = config('sequenceable.locking.retry_interval', 100);
        $startTime = microtime(true);

        while (true) {
            try {
                return DB::connection($connectionName)->transaction(function () use ($module, $typeCode, $period, $scope, $formatTemplate) {
                    return $this->incrementDatabaseSequence($module, $typeCode, $period, $scope, $formatTemplate, 1);
                });
            } catch (\Throwable $e) {
                if ((microtime(true) - $startTime) >= $timeoutSeconds) {
                    throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode}", $timeoutSeconds);
                }
                usleep($retryIntervalMs * 1000);
            }
        }
    }

    /**
     * Generate next number using Hi/Lo pre-allocation caching.
     */
    protected function generateViaPreAllocation(
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate
    ): array {
        $cacheKey = $this->getPreAllocationCacheKey($module, $typeCode, $period, $scope);
        $blockSize = (int) config('sequenceable.pre_allocation.block_size', 50);
        $timeoutSeconds = config('sequenceable.locking.timeout', 5);

        // Fetch current block from cache
        $cached = Cache::get($cacheKey);

        if ($cached && $cached['current'] < $cached['max']) {
            $newCurrent = $cached['current'] + 1;
            $cached['current'] = $newCurrent;
            Cache::put($cacheKey, $cached, 86400); // Cache for 24h

            return [
                'number' => $newCurrent,
                'template' => $cached['template'],
            ];
        }

        // Cache empty or exhausted, fetch next block from database
        $lockKey = "sequence_lock:pre_allocation:{$module}:{$typeCode}:{$period}:{$scope}";
        $lockStore = config('sequenceable.locking.cache_store');
        $lock = Cache::store($lockStore)->lock($lockKey, $timeoutSeconds);

        try {
            if (!$lock->block($timeoutSeconds)) {
                throw SequenceLockException::lockAcquisitionFailed("{$module}:{$typeCode} (pre-allocation)", $timeoutSeconds);
            }

            // Double check cache after acquiring lock
            $cached = Cache::get($cacheKey);
            if ($cached && $cached['current'] < $cached['max']) {
                $newCurrent = $cached['current'] + 1;
                $cached['current'] = $newCurrent;
                Cache::put($cacheKey, $cached, 86400);

                return [
                    'number' => $newCurrent,
                    'template' => $cached['template'],
                ];
            }

            // Increment database by block size
            $connectionName = config('sequenceable.connection');
            $dbResult = DB::connection($connectionName)->transaction(function () use ($module, $typeCode, $period, $scope, $formatTemplate, $blockSize) {
                return $this->incrementDatabaseSequence($module, $typeCode, $period, $scope, $formatTemplate, $blockSize);
            });

            $max = $dbResult['number'];
            $current = $max - $blockSize + 1;

            Cache::put($cacheKey, [
                'current' => $current,
                'max' => $max,
                'template' => $dbResult['template'],
            ], 86400);

            return [
                'number' => $current,
                'template' => $dbResult['template'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Atomically fetch and increment the database sequence record.
     */
    protected function incrementDatabaseSequence(
        string $module,
        string $typeCode,
        string $period,
        string $scope,
        ?string $formatTemplate,
        int $incrementBy
    ): array {
        $userId = Auth::id();
        $auditEnabled = config('sequenceable.audit.enabled', false);
        $createdByColumn = config('sequenceable.audit.created_by_column', 'created_by');
        $updatedByColumn = config('sequenceable.audit.updated_by_column', 'updated_by');

        $sequence = Sequence::where('module', $module)
            ->where('type_code', $typeCode)
            ->where('period', $period)
            ->where('scope', $scope)
            ->lockForUpdate()
            ->first();

        if (!$sequence) {
            $attributes = [
                'module' => $module,
                'type_code' => $typeCode,
                'period' => $period,
                'scope' => $scope,
                'current_number' => $incrementBy,
                'format_template' => $formatTemplate,
            ];

            if ($auditEnabled && $userId) {
                $attributes[$createdByColumn] = $userId;
                $attributes[$updatedByColumn] = $userId;
            }

            $sequence = Sequence::create($attributes);
        } else {
            // Update template if provided and different
            if ($formatTemplate && $sequence->format_template !== $formatTemplate) {
                $sequence->format_template = $formatTemplate;
            }

            $sequence->current_number += $incrementBy;

            if ($auditEnabled && $userId) {
                $sequence->setAttribute($updatedByColumn, $userId);
            }

            $sequence->save();
        }

        return [
            'number' => $sequence->current_number,
            'template' => $sequence->format_template,
        ];
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

        if (!$template) {
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

        // Replace model attributes: {attribute:field} or {field:field}
        if ($model) {
            $result = preg_replace_callback('/\{(attribute|field):([a-zA-Z0-9_]+)\}/', function ($matches) use ($model) {
                return $model->getAttribute($matches[2]) ?? '';
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
        return "sequenceable_pool:{$module}:{$typeCode}:{$period}:{$scope}";
    }
}
