<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Console;

use Illuminate\Support\Facades\Schema;
use MadeByClowd\AutoSequence\Tests\TestCase;

abstract class ConsoleTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing'])->run();

        Schema::create('test_invoices', function ($table) {
            $table->id();
            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('custom_ref')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        @unlink(config_path('auto-sequence.php'));

        parent::tearDown();
    }
}
