<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\DB;
use MadeByClowd\AutoSequence\Exceptions\AutoSequenceException;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestContinuousInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestContinuousMaxInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestSoftDeleteInvoice;

class RecyclingTest extends SequencingTestCase
{
    /** @test */
    public function test_it_recycles_continuous_sequence_on_model_deletion()
    {
        $inv1 = TestContinuousInvoice::create(); // CN-1
        $inv2 = TestContinuousInvoice::create(); // CN-2
        $inv3 = TestContinuousInvoice::create(); // CN-3

        $this->assertEquals('CN-1', $inv1->seq_continuous);
        $this->assertEquals('CN-2', $inv2->seq_continuous);
        $this->assertEquals('CN-3', $inv3->seq_continuous);

        // Delete inv2 (CN-2)
        $inv2->delete();

        // The next created invoice should recycle CN-2
        $inv4 = TestContinuousInvoice::create();
        $this->assertEquals('CN-2', $inv4->seq_continuous);

        // The one after should be CN-4
        $inv5 = TestContinuousInvoice::create();
        $this->assertEquals('CN-4', $inv5->seq_continuous);
    }

    /** @test */
    public function test_soft_deletes_do_not_recycle_sequence_numbers()
    {
        $inv1 = TestSoftDeleteInvoice::create(); // SD-1
        $inv2 = TestSoftDeleteInvoice::create(); // SD-2

        $this->assertEquals('SD-1', $inv1->seq_soft);
        $this->assertEquals('SD-2', $inv2->seq_soft);

        // Soft delete inv1
        $inv1->delete();

        // Ensure the sequence is NOT in the recycle table
        $recycledTable = config('auto-sequence.recycled_table', 'sequence_recycled');
        $exists = DB::table($recycledTable)
            ->where('module', 'adv_soft')
            ->where('number', 1)
            ->exists();
        $this->assertFalse($exists);

        // Next created invoice should be SD-3, not SD-1
        $inv3 = TestSoftDeleteInvoice::create();
        $this->assertEquals('SD-3', $inv3->seq_soft);
    }

    /** @test */
    public function test_force_deletes_do_recycle_sequence_numbers()
    {
        $inv1 = TestSoftDeleteInvoice::create(); // SD-1
        $inv2 = TestSoftDeleteInvoice::create(); // SD-2

        // Force delete inv1
        $inv1->forceDelete();

        // Ensure the sequence IS in the recycle table
        $recycledTable = config('auto-sequence.recycled_table', 'sequence_recycled');
        $exists = DB::table($recycledTable)
            ->where('module', 'adv_soft')
            ->where('number', 1)
            ->exists();
        $this->assertTrue($exists);

        // Next created invoice should recycle SD-1
        $inv3 = TestSoftDeleteInvoice::create();
        $this->assertEquals('SD-1', $inv3->seq_soft);
    }

    /** @test */
    public function test_recycling_the_same_number_twice_does_not_create_duplicate_entries()
    {
        // Fixture config uses period => 'never', which HasSequenceNumber resolves
        // to the literal period partition 'global'.
        Sequence::recycle('adv_continuous', 'CN', 'global', 'default', 5);
        Sequence::recycle('adv_continuous', 'CN', 'global', 'default', 5);

        $recycledTable = config('auto-sequence.recycled_table', 'sequence_recycled');
        $count = DB::table($recycledTable)
            ->where('module', 'adv_continuous')
            ->where('type_code', 'CN')
            ->where('number', 5)
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function test_it_enforces_max_value_on_a_recycled_number()
    {
        Sequence::recycle('adv_continuous_max', 'CM', 'global', 'default', 5);

        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage('Sequence [adv_continuous_max][CM] has exceeded its maximum limit of 2.');

        TestContinuousMaxInvoice::create();
    }
}
