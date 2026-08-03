<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\Cache;
use MadeByClowd\AutoSequence\Exceptions\AutoSequenceException;
use MadeByClowd\AutoSequence\Exceptions\SequenceLockException;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\NonLockingCacheStore;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\RaceConditionCacheStore;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;

class PreAllocationTest extends SequencingTestCase
{
    /** @test */
    public function test_pre_allocation_cache_increments_atomically()
    {
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.pre_allocation.block_size' => 5]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);

        $invoice1 = TestInvoice::create();
        $invoice2 = TestInvoice::create();
        $invoice3 = TestInvoice::create();

        $currentPeriod = now()->format('Ym');
        $this->assertEquals("INV-{$currentPeriod}-00001", $invoice1->number);
        $this->assertEquals("INV-{$currentPeriod}-00002", $invoice2->number);
        $this->assertEquals("INV-{$currentPeriod}-00003", $invoice3->number);

        // Database counter should be advanced by the block size (5)
        $dbVal = Sequence::getCurrent('invoice', 'INV', $currentPeriod);
        $this->assertEquals(5, $dbVal);
    }

    /** @test */
    public function test_pre_allocation_gapless_configuration_guard()
    {
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gapless']);

        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage("High-performance pre-allocation cannot be used with 'gapless' transaction mode.");

        TestInvoice::create();
    }

    /** @test */
    public function test_pre_allocation_uses_configured_cache_store()
    {
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['auto-sequence.pre_allocation.store' => 'array']);

        $invoice = TestInvoice::create();
        $this->assertNotNull($invoice->number);

        $currentPeriod = now()->format('Ym');
        $cacheKey = "auto-sequence_pool:invoice:INV:{$currentPeriod}:default";

        $this->assertTrue(Cache::store('array')->has($cacheKey));
    }

    /** @test */
    public function test_pre_allocation_refill_fails_when_lock_store_does_not_support_atomic_locks()
    {
        Cache::extend('no_lock', fn () => Cache::repository(new NonLockingCacheStore));
        config(['cache.stores.no_lock_store' => ['driver' => 'no_lock']]);
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['auto-sequence.locking.cache_store' => 'no_lock_store']);

        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage('does not support atomic locks');

        TestInvoice::create();
    }

    /** @test */
    public function test_pre_allocation_refill_throws_when_the_refill_lock_is_already_held()
    {
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['auto-sequence.locking.cache_store' => 'array']);
        config(['auto-sequence.locking.timeout' => 0]);

        // Hold the refill lock ourselves first so the manager can never acquire it.
        $lockKey = 'sequence_lock:pre_allocation:pa_lock_test:PL:global:default';
        $heldLock = Cache::store('array')->lock($lockKey, 10);
        $this->assertTrue($heldLock->get());

        $this->expectException(SequenceLockException::class);

        Sequence::generate('pa_lock_test', 'PL', 'global', 'PL-{seq:2}', 2);
    }

    /** @test */
    public function test_pre_allocation_uses_the_freshly_refilled_cache_if_another_process_wins_the_race()
    {
        Cache::extend('race', fn () => Cache::repository(new RaceConditionCacheStore));
        config(['cache.stores.race_store' => ['driver' => 'race']]);
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['auto-sequence.pre_allocation.store' => 'race_store']);
        config(['auto-sequence.locking.cache_store' => 'array']);

        // Simulate another process having just refilled the block: real data
        // is sitting in the store, but our custom store forces the *first*
        // cache read to miss anyway, so the manager proceeds to acquire the
        // refill lock. The "double check after acquiring the lock" read must
        // then find this fresh block and use it instead of hitting the DB.
        $cacheKey = 'auto-sequence_pool:race_test:RC:global:default';
        Cache::store('race_store')->put($cacheKey, [
            'current' => 10,
            'max' => 15,
            'template' => 'RC-{seq:2}',
        ], 86400);

        $result = Sequence::generate('race_test', 'RC', 'global', 'RC-{seq:2}', 2);

        $this->assertEquals('RC-11', $result);

        // The database counter must never have been touched.
        $this->assertEquals(0, Sequence::getCurrent('race_test', 'RC', 'global'));
    }

    /** @test */
    public function test_reset_clears_the_pre_allocation_cache_so_stale_blocks_are_not_reused()
    {
        config(['auto-sequence.pre_allocation.enabled' => true]);
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['auto-sequence.pre_allocation.block_size' => 5]);

        // Fetches and caches a block of 5 (1-5); returns 1.
        $first = Sequence::generate('reset_cache_test', 'RC2', 'global', 'RC2-{seq:2}', 2);
        $this->assertEquals('RC2-01', $first);

        Sequence::reset('reset_cache_test', 'RC2', 'global', 'default', 100);

        // Without the cache being cleared, this would return the next cached
        // value (2) instead of continuing on from the new reset value.
        $next = Sequence::generate('reset_cache_test', 'RC2', 'global', 'RC2-{seq:2}', 2);
        $this->assertEquals('RC2-101', $next);
    }
}
