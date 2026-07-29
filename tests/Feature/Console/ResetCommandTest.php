<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use MadeByClowd\AutoSequence\Facades\Sequence;

class ResetCommandTest extends ConsoleTestCase
{
    /** @test */
    public function test_it_can_be_cancelled()
    {
        $currentPeriod = now()->format('Ym');
        Sequence::reset('invoice', 'INV', $currentPeriod, 'default', 50);

        $this->artisan('sequence:reset invoice INV --value=999')
            ->expectsConfirmation(
                'Are you sure you want to reset the sequence [invoice][INV]['.$currentPeriod.'][default] to 999?',
                'no'
            )
            ->expectsOutputToContain('Reset cancelled.')
            ->assertExitCode(0);

        $this->assertEquals(50, Sequence::getCurrent('invoice', 'INV', $currentPeriod));
    }

    /** @test */
    public function test_it_resets_a_custom_scope_without_affecting_the_default_scope()
    {
        $currentPeriod = now()->format('Ym');
        Sequence::reset('invoice', 'INV', $currentPeriod, 'custom-scope', 77);

        $this->artisan('sequence:reset invoice INV --scope=custom-scope --value=200')
            ->expectsConfirmation(
                'Are you sure you want to reset the sequence [invoice][INV]['.$currentPeriod.'][custom-scope] to 200?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertEquals(200, Sequence::getCurrent('invoice', 'INV', $currentPeriod, 'custom-scope'));
        $this->assertEquals(0, Sequence::getCurrent('invoice', 'INV', $currentPeriod, 'default'));
    }
}
