<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\Event;
use MadeByClowd\AutoSequence\Events\SequenceExhausted;
use MadeByClowd\AutoSequence\Events\SequenceGenerated;
use MadeByClowd\AutoSequence\Events\SequenceRecycled;
use MadeByClowd\AutoSequence\Events\SequenceResetPerformed;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestExhaustionInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestMaxInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestSoftDeleteInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestStartInvoice;

class EventsTest extends SequencingTestCase
{
    /**
     * Note: this suite deliberately scopes Event::fake() to specific event
     * classes rather than faking everything. A bare Event::fake() would
     * also intercept Eloquent's own 'creating' event, which is exactly what
     * HasSequenceNumber::bootHasSequenceNumber() listens on to generate the
     * number in the first place — that would silently break generation for
     * every test in this file.
     */

    /** @test */
    public function test_sequence_generated_dispatches_with_correct_payload()
    {
        Event::fake([SequenceGenerated::class]);

        $invoice = TestStartInvoice::create();

        Event::assertDispatched(SequenceGenerated::class, function ($event) use ($invoice) {
            return $event->module === 'adv_start'
                && $event->typeCode === 'ST'
                && $event->period === 'global'
                && $event->scope === 'default'
                && $event->number === $invoice->seq_start
                && $event->model->is($invoice);
        });
    }

    /** @test */
    public function test_sequence_exhausted_uses_per_sequence_threshold_override_and_resets_flag_on_reset()
    {
        Event::fake([SequenceExhausted::class]);

        // max_value=10, exhaustion_threshold=50 => fires once current_number >= 5.
        // Global default is 90, so firing at the 5th (not 9th) creation proves
        // the per-sequence override took precedence.
        for ($i = 0; $i < 5; $i++) {
            TestExhaustionInvoice::create();
        }
        Event::assertDispatchedTimes(SequenceExhausted::class, 1);

        // Still past threshold, but already notified for this partition — no re-fire.
        TestExhaustionInvoice::create();
        Event::assertDispatchedTimes(SequenceExhausted::class, 1);

        // Sequence::reset() clears the notified flag alongside the counter.
        Sequence::reset('adv_exhaustion', 'EX', 'global', 'default', 0);

        for ($i = 0; $i < 5; $i++) {
            TestExhaustionInvoice::create();
        }
        Event::assertDispatchedTimes(SequenceExhausted::class, 2);
    }

    /** @test */
    public function test_sequence_exhausted_uses_global_default_threshold_when_no_override()
    {
        Event::fake([SequenceExhausted::class]);

        // TestMaxInvoice: max_value=3, no exhaustion_threshold override.
        // Global default is 90%, so threshold value = 3 * 0.9 = 2.7 — only
        // the 3rd creation (current_number=3) should cross it.
        TestMaxInvoice::create();
        Event::assertNotDispatched(SequenceExhausted::class);

        TestMaxInvoice::create();
        Event::assertNotDispatched(SequenceExhausted::class);

        TestMaxInvoice::create();
        Event::assertDispatchedTimes(SequenceExhausted::class, 1);
    }

    /** @test */
    public function test_sequence_exhausted_never_fires_without_max_value()
    {
        Event::fake([SequenceExhausted::class]);

        // TestStartInvoice has no max_value configured at all.
        for ($i = 0; $i < 5; $i++) {
            TestStartInvoice::create();
        }

        Event::assertNotDispatched(SequenceExhausted::class);
    }

    /** @test */
    public function test_sequence_recycled_dispatches_on_force_delete_not_soft_delete()
    {
        Event::fake([SequenceRecycled::class]);

        $inv1 = TestSoftDeleteInvoice::create();
        $inv2 = TestSoftDeleteInvoice::create();

        $inv1->delete(); // soft delete
        Event::assertNotDispatched(SequenceRecycled::class);

        $inv2->forceDelete();
        Event::assertDispatched(SequenceRecycled::class, function ($event) {
            return $event->module === 'adv_soft'
                && $event->typeCode === 'SD'
                && $event->number === 2;
        });
    }

    /** @test */
    public function test_sequence_reset_performed_dispatches_via_facade_and_command()
    {
        Event::fake([SequenceResetPerformed::class]);

        Sequence::reset('reset_evt', 'RE', 'global', 'default', 42);

        Event::assertDispatched(SequenceResetPerformed::class, function ($event) {
            return $event->module === 'reset_evt'
                && $event->typeCode === 'RE'
                && $event->resetTo === 42;
        });

        $this->artisan('sequence:reset reset_evt RE --period=global --value=7')
            ->expectsConfirmation(
                'Are you sure you want to reset the sequence [reset_evt][RE][global][default] to 7?',
                'yes'
            )
            ->assertExitCode(0);

        Event::assertDispatchedTimes(SequenceResetPerformed::class, 2);
    }
}
