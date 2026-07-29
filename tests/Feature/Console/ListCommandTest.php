<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use MadeByClowd\AutoSequence\Facades\Sequence;

class ListCommandTest extends ConsoleTestCase
{
    /** @test */
    public function test_it_reports_when_no_sequences_exist()
    {
        $this->artisan('sequence:list')
            ->expectsOutputToContain('No active sequence counters found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_filters_by_module_and_scope()
    {
        Sequence::generate('invoice', 'INV', '202601', 'INV-{seq:3}', 3, 'tenant-a');
        Sequence::generate('order', 'ORD', '202601', 'ORD-{seq:3}', 3, 'tenant-b');

        $this->artisan('sequence:list', ['--module' => 'invoice'])
            ->expectsOutputToContain('invoice')
            ->assertExitCode(0);

        $this->artisan('sequence:list', ['--scope' => 'tenant-b'])
            ->expectsOutputToContain('order')
            ->assertExitCode(0);
    }
}
