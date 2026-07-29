<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestBranch;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestClosureInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestRelationDotInvoice;

class TemplateFormattingTest extends SequencingTestCase
{
    /** @test */
    public function test_it_supports_custom_date_formats()
    {
        $invoice = TestInvoice::create();

        $formatted = Sequence::generate(
            'test_date',
            'DT',
            'global',
            'DT-{date:d-m-Y}-{seq:3}',
            3,
            'default',
            $invoice
        );

        $expectedDate = now()->format('d-m-Y');
        $this->assertEquals("DT-{$expectedDate}-001", $formatted);
    }

    /** @test */
    public function test_it_resolves_type_code_variations()
    {
        $invoice = TestInvoice::create();

        // 1. Using prefix before {type_code}
        $formatted1 = Sequence::generate('test_var', 'INV', 'global', 'BDG{type_code}-{YYYY}-{seq:2}', 2, 'default', $invoice);
        $currentYear = now()->format('Y');
        $this->assertEquals("BDGINV-{$currentYear}-01", $formatted1);

        // 2. Using alias {type-code}
        $formatted2 = Sequence::generate('test_var2', 'INV', 'global', 'BDG{type-code}-{YYYY}-{seq:2}', 2, 'default', $invoice);
        $this->assertEquals("BDGINV-{$currentYear}-01", $formatted2);

        // 3. Using alias {typeCode}
        $formatted3 = Sequence::generate('test_var3', 'INV', 'global', 'BDG{typeCode}-{YYYY}-{seq:2}', 2, 'default', $invoice);
        $this->assertEquals("BDGINV-{$currentYear}-01", $formatted3);
    }

    /** @test */
    public function test_it_supports_closure_format_templates()
    {
        $inv1 = TestClosureInvoice::create();
        $this->assertEquals('CL-NEW-1', $inv1->seq_closure);
    }

    /** @test */
    public function test_it_resolves_nested_relation_dot_notation()
    {
        $branch = TestBranch::create(['name' => 'Tokyo Branch', 'code' => 'HND']);
        $inv = TestRelationDotInvoice::create(['branch_id' => $branch->id]);
        $this->assertEquals('RD-HND-1', $inv->seq_relation_dot);
    }

    /** @test */
    public function test_it_replaces_module_and_scope_placeholders()
    {
        $formatted = Sequence::generate(
            'billing',
            'BIL',
            '202601',
            '{module}-{scope}-{seq:3}',
            3,
            'eu'
        );

        $this->assertEquals('BILLING-EU-001', $formatted);
    }

    /** @test */
    public function test_it_replaces_random_string_placeholders()
    {
        $formatted = Sequence::generate('random_test', 'RND', '202601', 'RND-{rand:6}-{seq:2}', 2);

        $this->assertMatchesRegularExpression('/^RND-[A-Za-z0-9]{6}-01$/', $formatted);
    }

    /** @test */
    public function test_it_replaces_missing_attribute_with_empty_string()
    {
        $invoice = TestInvoice::create();

        $formatted = Sequence::generate(
            'attr_missing',
            'AM',
            'global',
            'AM-{attribute:nonexistent_field}-{seq:2}',
            2,
            'default',
            $invoice
        );

        $this->assertEquals('AM--01', $formatted);
    }
}
