<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MadeByClowd\AutoSequence\Exceptions\AutoSequenceException;
use MadeByClowd\AutoSequence\Exceptions\SequenceLockException;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\NonLockingCacheStore;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;

class LockingTest extends SequencingTestCase
{
    /** @test */
    public function test_database_lock_timeout_is_applied()
    {
        config(['auto-sequence.locking.driver' => 'database']);
        config(['auto-sequence.locking.timeout' => 3]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        TestInvoice::create();

        $hasPragma = false;
        foreach ($queries as $sql) {
            if (str_contains($sql, 'PRAGMA busy_timeout = 3000')) {
                $hasPragma = true;
                break;
            }
        }

        $this->assertTrue($hasPragma, 'PRAGMA busy_timeout = 3000 was not executed on SQLite connection');
    }

    /** @test */
    public function test_cache_locking_driver_generates_sequence_numbers()
    {
        config(['auto-sequence.locking.driver' => 'cache']);
        config(['auto-sequence.locking.cache_store' => 'array']);

        $invoice = TestInvoice::create();

        $currentPeriod = now()->format('Ym');
        $this->assertEquals("INV-{$currentPeriod}-00001", $invoice->number);
    }

    /** @test */
    public function test_cache_locking_fails_when_store_does_not_support_atomic_locks()
    {
        Cache::extend('no_lock', fn () => Cache::repository(new NonLockingCacheStore));
        config(['cache.stores.no_lock_store' => ['driver' => 'no_lock']]);
        config(['auto-sequence.locking.driver' => 'cache']);
        config(['auto-sequence.locking.cache_store' => 'no_lock_store']);

        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage('does not support atomic locks');

        TestInvoice::create();
    }

    /** @test */
    public function test_cache_locking_throws_when_lock_is_already_held()
    {
        config(['auto-sequence.locking.driver' => 'cache']);
        config(['auto-sequence.locking.cache_store' => 'array']);
        config(['auto-sequence.locking.timeout' => 0]);

        // Hold the lock ourselves first so the manager can never acquire it.
        $lockKey = 'sequence_lock:lock_test:LK:202601:own-scope';
        $heldLock = Cache::store('array')->lock($lockKey, 10);
        $this->assertTrue($heldLock->get());

        $this->expectException(SequenceLockException::class);

        Sequence::generate('lock_test', 'LK', '202601', 'LK-{seq:2}', 2, 'own-scope');
    }

    /** @test */
    public function test_database_locking_wraps_unexpected_transaction_errors_in_a_sequence_lock_exception()
    {
        config(['auto-sequence.locking.driver' => 'database']);

        // Any error inside the locked transaction (not just a lock timeout)
        // should be caught and rethrown as a SequenceLockException.
        Schema::drop(config('auto-sequence.table', 'sequences'));

        $this->expectException(SequenceLockException::class);

        Sequence::generate('db_error_test', 'DE', '202601', 'DE-{seq:2}', 2);
    }
}
