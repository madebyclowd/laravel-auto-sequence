<?php

namespace MadeByClowd\Sequenceable\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use MadeByClowd\Sequenceable\Contracts\Sequenceable;
use MadeByClowd\Sequenceable\Facades\Sequence;
use MadeByClowd\Sequenceable\Traits\HasSequenceNumber;
use MadeByClowd\Sequenceable\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class HasSequenceNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for the package
        $this->artisan('migrate', ['--database' => 'testing'])->run();

        // Create test tables
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
    }

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
    public function test_pre_allocation_cache_increments_atomically()
    {
        config(['sequenceable.pre_allocation.enabled' => true]);
        config(['sequenceable.pre_allocation.block_size' => 5]);

        $invoice1 = TestInvoice::create();
        $invoice2 = TestInvoice::create();
        $invoice3 = TestInvoice::create();

        $currentPeriod = now()->format('Ym');
        $this->assertEquals("INV-{$currentPeriod}-00001", $invoice1->number);
        $this->assertEquals("INV-{$currentPeriod}-00002", $invoice2->number);
        $this->assertEquals("INV-{$currentPeriod}-00003", $invoice3->number);

        // Database counter should be advanced by the block size (5)
        $dbVal = Sequence::getCurrent('invoice', 'INV', $currentPeriod);
        $this->assertEquals(5, $dbVal);
    }

    /** @test */
    public function test_artisan_list_and_reset_commands()
    {
        TestInvoice::create();

        // List Command
        $this->artisan('sequence:list')
            ->assertExitCode(0);

        $currentPeriod = now()->format('Ym');
        $dbValBefore = Sequence::getCurrent('invoice', 'INV', $currentPeriod);
        $this->assertEquals(1, $dbValBefore);

        // Reset Command
        $this->artisan('sequence:reset invoice INV --value=100')
            ->expectsConfirmation('Are you sure you want to reset the sequence [invoice][INV][' . $currentPeriod . '][default] to 100?', 'yes')
            ->assertExitCode(0);

        $dbValAfter = Sequence::getCurrent('invoice', 'INV', $currentPeriod);
        $this->assertEquals(100, $dbValAfter);

        $invoiceNew = TestInvoice::create();
        $this->assertEquals("INV-{$currentPeriod}-00101", $invoiceNew->number);
    }

    /** @test */
    public function test_artisan_verify_and_repair_command()
    {
        $currentPeriod = now()->format('Ym');

        // Create records
        TestInvoice::create(); // number is INV-202606-00001
        TestInvoice::create(); // number is INV-202606-00002

        // Manually decrease DB counter to simulate drift
        Sequence::reset('invoice', 'INV', $currentPeriod, 'default', 0);

        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
            '--type' => 'INV',
            '--module' => 'invoice',
        ])
            ->expectsOutputToContain('Drift detected! Database counter is behind')
            ->assertExitCode(0);

        // Verify and Repair
        $this->artisan('sequence:verify', [
            'model' => TestInvoice::class,
            'column' => 'number',
            '--type' => 'INV',
            '--module' => 'invoice',
            '--repair' => true,
        ])
            ->expectsOutputToContain('Successfully repaired database sequence counter to 2')
            ->assertExitCode(0);

        // Database value should now be 2
        $this->assertEquals(2, Sequence::getCurrent('invoice', 'INV', $currentPeriod));
    }

    /** @test */
    public function test_it_automatically_publishes_boost_skills_when_boost_commands_run()
    {
        $targetSkillPath = base_path('.github/skills/laravel-sequenceable/SKILL.md');
        $boostJsonPath = base_path('boost.json');

        if (file_exists($targetSkillPath)) {
            unlink($targetSkillPath);
        }
        if (file_exists($boostJsonPath)) {
            unlink($boostJsonPath);
        }

        file_put_contents($boostJsonPath, json_encode([
            'skills' => ['laravel-best-practices'],
        ]));

        $this->assertFileDoesNotExist($targetSkillPath);

        Event::dispatch(
            new CommandFinished(
                'boost:install',
                new ArrayInput([]),
                new NullOutput,
                0
            )
        );

        $this->assertFileExists($targetSkillPath);

        $boostJson = json_decode(file_get_contents($boostJsonPath), true);
        $this->assertContains('laravel-sequenceable', $boostJson['skills']);

        // Cleanup
        if (file_exists($targetSkillPath)) {
            unlink($targetSkillPath);
            if (is_dir(dirname($targetSkillPath))) {
                rmdir(dirname($targetSkillPath));
            }
        }
        if (file_exists($boostJsonPath)) {
            unlink($boostJsonPath);
        }
    }

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
}

// Test Models definition
class TestBranch extends Model
{
    protected $fillable = ['name', 'code'];
    protected $table = 'test_branches';
}

class TestInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['number', 'reference', 'custom_ref', 'branch_id', 'tenant_id'];
    protected $table = 'test_invoices';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(TestBranch::class);
    }

    public function getSequenceConfig(): array
    {
        return [
            'number' => [
                'module' => 'invoice',
                'type_code' => 'INV',
                'period' => 'monthly',
                'scope' => 'tenant_id',
                'format_template' => 'INV-{period}-{seq:5}',
                'pad_length' => 5,
            ],
            'reference' => [
                'module' => 'invoice_ref',
                'type_relation' => 'branch',
                'default_type' => 'HQ',
                'period' => 'yearly',
                'format_template' => '{type_code}-{YYYY}-{seq:3}',
                'pad_length' => 3,
            ],
            'custom_ref' => [
                'module' => 'invoice_custom',
                'type_code' => 'CUST',
                'period' => function ($model) {
                    return 'custom-prefix';
                },
                'format_template' => '{period}-{seq:3}',
                'pad_length' => 3,
            ],
        ];
    }
}
