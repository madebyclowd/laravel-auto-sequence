<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Support\Facades\Schema;
use MadeByClowd\AutoSequence\Tests\TestCase;

abstract class SequencingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing'])->run();

        Schema::create('test_branches', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('test_invoices', function ($table) {
            $table->id();
            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('custom_ref')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_start_invoices', function ($table) {
            $table->id();
            $table->string('seq_start')->nullable();
            $table->timestamps();
        });

        Schema::create('test_step_invoices', function ($table) {
            $table->id();
            $table->string('seq_step')->nullable();
            $table->timestamps();
        });

        Schema::create('test_max_invoices', function ($table) {
            $table->id();
            $table->string('seq_max')->nullable();
            $table->timestamps();
        });

        Schema::create('test_exhaustion_invoices', function ($table) {
            $table->id();
            $table->string('seq_exhaustion')->nullable();
            $table->timestamps();
        });

        Schema::create('test_no_manual_invoices', function ($table) {
            $table->id();
            $table->string('seq_no_manual')->nullable();
            $table->timestamps();
        });

        Schema::create('test_closure_invoices', function ($table) {
            $table->id();
            $table->string('seq_closure')->nullable();
            $table->timestamps();
        });

        Schema::create('test_relation_dot_invoices', function ($table) {
            $table->id();
            $table->string('seq_relation_dot')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_continuous_invoices', function ($table) {
            $table->id();
            $table->string('seq_continuous')->nullable();
            $table->timestamps();
        });

        Schema::create('test_soft_delete_invoices', function ($table) {
            $table->id();
            $table->string('seq_soft')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('test_continuous_max_invoices', function ($table) {
            $table->id();
            $table->string('seq_continuous_max')->nullable();
            $table->timestamps();
        });

        Schema::create('test_shorthand_invoices', function ($table) {
            $table->id();
            $table->string('number')->nullable();
            $table->timestamps();
        });

        Schema::create('test_plain_trait_invoices', function ($table) {
            $table->id();
            $table->string('number')->nullable();
            $table->timestamps();
        });

        Schema::create('test_relation_array_invoices', function ($table) {
            $table->id();
            $table->string('seq_relation_array')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_default_type_invoices', function ($table) {
            $table->id();
            $table->string('seq_default_type')->nullable();
            $table->timestamps();
        });

        Schema::create('test_custom_period_invoices', function ($table) {
            $table->id();
            $table->string('seq_custom_period')->nullable();
            $table->timestamps();
        });

        Schema::create('test_period_variant_invoices', function ($table) {
            $table->id();
            $table->string('seq_daily')->nullable();
            $table->string('seq_weekly')->nullable();
            $table->timestamps();
        });

        Schema::create('test_scope_closure_invoices', function ($table) {
            $table->id();
            $table->string('seq_scope_closure')->nullable();
            $table->timestamps();
        });

        Schema::create('test_scope_class_invoices', function ($table) {
            $table->id();
            $table->string('seq_scope_class')->nullable();
            $table->timestamps();
        });
    }
}
