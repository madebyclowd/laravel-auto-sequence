<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Facades\Sequence;

class ConnectionResolutionTest extends SequencingTestCase
{
    /** @test */
    public function test_it_resolves_isolated_connection_names_correctly()
    {
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['database.connections.mysql_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
        ]]);

        $resolved = Sequence::resolveConnectionName('mysql_test');
        $this->assertEquals('auto-sequence_isolated_mysql_test', $resolved);
        $this->assertTrue(config()->has('database.connections.auto-sequence_isolated_mysql_test'));
        $this->assertEquals('mysql', config('database.connections.auto-sequence_isolated_mysql_test.driver'));
    }

    /** @test */
    public function test_it_reuses_an_already_isolated_connection_without_recreating_it()
    {
        config(['auto-sequence.transaction_mode' => 'gap_tolerant']);
        config(['database.connections.mysql_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
        ]]);

        Sequence::resolveConnectionName('mysql_test');

        // Mutate the isolated config directly to prove a second resolve doesn't overwrite it.
        config(['database.connections.auto-sequence_isolated_mysql_test.host' => 'mutated']);

        $resolved = Sequence::resolveConnectionName('mysql_test');

        $this->assertEquals('auto-sequence_isolated_mysql_test', $resolved);
        $this->assertEquals('mutated', config('database.connections.auto-sequence_isolated_mysql_test.host'));
    }
}
