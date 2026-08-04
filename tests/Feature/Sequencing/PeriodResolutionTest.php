<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Carbon;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;

class PeriodResolutionTest extends SequencingTestCase
{
    /** @test */
    public function test_it_resolves_quarterly_period_across_quarter_boundaries()
    {
        $cases = [
            '2026-01-15' => '2026Q1',
            '2026-03-31' => '2026Q1',
            '2026-04-01' => '2026Q2',
            '2026-12-31' => '2026Q4',
        ];

        foreach ($cases as $date => $expected) {
            $invoice = new TestInvoice;
            $invoice->created_at = Carbon::parse($date);

            $this->assertEquals($expected, $invoice->resolveSequencePeriod(['period' => 'quarterly']));
        }
    }

    /** @test */
    public function test_it_renders_quarterly_period_via_period_template_token()
    {
        $invoice = new TestInvoice;
        $invoice->created_at = Carbon::parse('2026-04-01');

        $formatted = Sequence::generate(
            'quarterly_test',
            'QT',
            $invoice->resolveSequencePeriod(['period' => 'quarterly']),
            'QT-{period}-{seq:2}',
            2,
            'default',
            $invoice
        );

        $this->assertEquals('QT-2026Q2-01', $formatted);
    }
}
