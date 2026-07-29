<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\Cache;
use MadeByClowd\AutoSequence\Exceptions\AutoSequenceException;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\NonLockingCacheStore;
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
}
