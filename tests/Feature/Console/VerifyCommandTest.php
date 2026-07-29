<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestBranch;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestInvoice;

class VerifyCommandTest extends ConsoleTestCase
{
    /** @test */
    public function test_it_fails_when_model_class_does_not_exist()
    {
        $this->artisan('sequence:verify', [
            'model' => 'App\\Models\\DoesNotExist',
            'column' => 'number',
        ])
            ->expectsOutputToContain("Model class 'App\\Models\\DoesNotExist' does not exist.")
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_class_is_not_an_eloquent_model()
    {
        $this->artisan('sequence:verify', [
            'model' => PlainNotAModel::class,
            'column' => 'number',
        ])
            ->expectsOutputToContain('is not a valid Eloquent Model subclass.')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_model_is_not_sequenceable()
    {
        $this->artisan('sequence:verify', [
            'model' => TestBranch::class,
            'column' => 'name',
        ])
            ->expectsOutputToContain("must implement 'MadeByClowd\\AutoSequence\\Contracts\\Sequenceable' interface.")
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_fails_when_column_does_not_exist()
    {
        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'nonexistent_column',
        ])
            ->expectsOutputToContain("does not have a column named 'nonexistent_column'.")
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_reports_no_records_found()
    {
        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
        ])
            ->expectsOutputToContain('No records found with sequence values')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_reports_in_sync_when_no_drift()
    {
        TestInvoice::create();

        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
            '--type' => 'INV',
            '--module' => 'invoice',
        ])
            ->expectsOutputToContain('Sequence is verified and in sync!')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_applies_the_period_filter_when_option_is_given()
    {
        TestInvoice::create();
        $currentPeriod = now()->format('Ym');

        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
            '--type' => 'INV',
            '--module' => 'invoice',
            '--period' => $currentPeriod,
        ])
            ->expectsOutputToContain('Sequence is verified and in sync!')
            ->assertExitCode(0);
    }

    /** @test */
    public function test_it_skips_the_type_filter_when_type_option_is_empty()
    {
        TestInvoice::create();

        // With --type='', the record scan matches on module alone, but getCurrent()
        // looks up the real stored type_code ('INV') so it finds no counter row (0)
        // — this is reported as drift rather than "in sync".
        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
            '--type' => '',
            '--module' => 'invoice',
        ])
            ->expectsOutputToContain('Highest sequence number found in model records: 1')
            ->expectsOutputToContain('Drift detected!')
            ->assertExitCode(0);
    }
}

class PlainNotAModel
{
    //
}
