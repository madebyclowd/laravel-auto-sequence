<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestBranch;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestStartInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestStepInvoice;

class GenerationTest extends SequencingTestCase
{
    /** @test */
    public function test_it_generates_basic_sequence_number_on_creation()
    {
        $invoice1 = TestInvoice::create();
        $invoice2 = TestInvoice::create();

        $currentPeriod = now()->format('Ym');

        $this->assertEquals("INV-{$currentPeriod}-00001", $invoice1->number);
        $this->assertEquals("INV-{$currentPeriod}-00002", $invoice2->number);
    }

    /** @test */
    public function test_it_resolves_type_code_via_relationship()
    {
        $branch = TestBranch::create(['name' => 'Bali Branch', 'code' => 'DPS']);

        $invoice = TestInvoice::create(['branch_id' => $branch->id]);

        $currentYear = now()->format('Y');
        $this->assertEquals("DPS-{$currentYear}-001", $invoice->reference);
    }

    /** @test */
    public function test_it_uses_fallback_default_type_if_relationship_is_missing()
    {
        $invoice = TestInvoice::create();

        $currentYear = now()->format('Y');
        $this->assertEquals("HQ-{$currentYear}-001", $invoice->reference);
    }

    /** @test */
    public function test_it_scopes_sequences_independently()
    {
        $invoiceT1_1 = TestInvoice::create(['tenant_id' => 'tenant-1']);
        $invoiceT2_1 = TestInvoice::create(['tenant_id' => 'tenant-2']);
        $invoiceT1_2 = TestInvoice::create(['tenant_id' => 'tenant-1']);

        $currentPeriod = now()->format('Ym');

        $this->assertEquals("INV-{$currentPeriod}-00001", $invoiceT1_1->number);
        $this->assertEquals("INV-{$currentPeriod}-00001", $invoiceT2_1->number);
        $this->assertEquals("INV-{$currentPeriod}-00002", $invoiceT1_2->number);
    }

    /** @test */
    public function test_it_respects_manual_override_values()
    {
        $invoice = TestInvoice::create(['number' => 'MANUAL-123']);

        $this->assertEquals('MANUAL-123', $invoice->number);

        // Next auto-generated invoice should start back at 1 since we bypassed
        $invoiceNext = TestInvoice::create();
        $currentPeriod = now()->format('Ym');
        $this->assertEquals("INV-{$currentPeriod}-00001", $invoiceNext->number);
    }

    /** @test */
    public function test_it_supports_custom_reset_callables()
    {
        $invoice = TestInvoice::create();

        // Custom callable formats period as 'custom-prefix'
        $this->assertEquals('custom-prefix-001', $invoice->custom_ref);
    }

    /** @test */
    public function test_it_supports_start_value()
    {
        $inv1 = TestStartInvoice::create();
        $this->assertEquals('ST-1000', $inv1->seq_start);

        $inv2 = TestStartInvoice::create();
        $this->assertEquals('ST-1001', $inv2->seq_start);
    }

    /** @test */
    public function test_it_supports_custom_step()
    {
        $inv1 = TestStepInvoice::create();
        $this->assertEquals('SP-1', $inv1->seq_step);

        $inv2 = TestStepInvoice::create();
        $this->assertEquals('SP-3', $inv2->seq_step);

        $inv3 = TestStepInvoice::create();
        $this->assertEquals('SP-5', $inv3->seq_step);
    }
}
