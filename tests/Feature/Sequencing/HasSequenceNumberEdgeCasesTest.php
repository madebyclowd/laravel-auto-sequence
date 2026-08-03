<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestBranch;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestCustomPeriodInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestDefaultTypeInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestPeriodVariantInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestPlainTraitInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestRelationArrayInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestScopeClassInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestScopeClosureInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestShorthandInvoice;

class HasSequenceNumberEdgeCasesTest extends SequencingTestCase
{
    /** @test */
    public function test_flat_shorthand_config_fills_the_default_number_column_and_recycles_on_force_delete()
    {
        $invoice = TestShorthandInvoice::create();
        $this->assertSame('SH-001', $invoice->number);

        $invoice->forceDelete();

        // Recycled number 1 should be handed back out first.
        $again = TestShorthandInvoice::create();
        $this->assertSame('SH-001', $again->number);
    }

    /** @test */
    public function test_models_using_the_trait_without_the_sequenceable_contract_are_ignored()
    {
        $model = TestPlainTraitInvoice::create();

        $this->assertNull($model->number);

        // Should not error even though it's not a Sequenceable model.
        $model->delete();
        $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
    }

    /** @test */
    public function test_type_relation_array_form_resolves_relation_and_column()
    {
        $branch = TestBranch::create(['name' => 'Jakarta', 'code' => 'JKT']);

        $invoice = TestRelationArrayInvoice::create(['branch_id' => $branch->id]);

        $this->assertSame('JKT-001', $invoice->seq_relation_array);
    }

    /** @test */
    public function test_type_relation_array_form_falls_back_to_default_type_without_relation()
    {
        $invoice = TestRelationArrayInvoice::create();

        $this->assertSame('HQ-001', $invoice->seq_relation_array);
    }

    /** @test */
    public function test_default_type_code_falls_back_to_gen_when_unconfigured()
    {
        $invoice = TestDefaultTypeInvoice::create();

        $this->assertSame('GEN-001', $invoice->seq_default_type);
    }

    /** @test */
    public function test_period_resolves_via_a_custom_resolver_class()
    {
        $invoice = TestCustomPeriodInvoice::create();

        $this->assertSame('CP-FY'.now()->year.'-001', $invoice->seq_custom_period);
    }

    /** @test */
    public function test_daily_and_weekly_period_keywords_resolve_correctly()
    {
        $invoice = TestPeriodVariantInvoice::create();

        $this->assertSame('DL-'.now()->format('Ymd').'-001', $invoice->seq_daily);
        $this->assertSame('WK-'.now()->format('oW').'-001', $invoice->seq_weekly);
    }

    /** @test */
    public function test_scope_resolves_via_a_closure()
    {
        $invoice = TestScopeClosureInvoice::create();

        $this->assertSame('SC-001', $invoice->seq_scope_closure);

        $dbValue = Sequence::getCurrent('adv_scope_closure', 'SC', 'global', 'closure-scope');
        $this->assertSame(1, $dbValue);
    }

    /** @test */
    public function test_scope_resolves_via_a_custom_resolver_class()
    {
        $invoice = TestScopeClassInvoice::create();

        $this->assertSame('RS-001', $invoice->seq_scope_class);

        $dbValue = Sequence::getCurrent('adv_scope_class', 'RS', 'global', 'region-resolved');
        $this->assertSame(1, $dbValue);
    }
}
