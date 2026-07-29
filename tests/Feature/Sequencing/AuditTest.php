<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\TestCase;

class AuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Audit columns are only added to the `sequences` table when this is
        // enabled *before* migrations run, so it must be set ahead of migrate.
        config(['auto-sequence.audit.enabled' => true]);

        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function sequencesTable()
    {
        return DB::table(config('auto-sequence.table', 'sequences'));
    }

    /** @test */
    public function test_it_stamps_created_by_and_updated_by_on_first_generation()
    {
        Auth::setUser(new FakeAuditUser(1));

        Sequence::generate('audit_mod', 'AUD', '202601', '{seq}', 3);

        $row = $this->sequencesTable()->where('module', 'audit_mod')->first();

        $this->assertEquals(1, $row->created_by);
        $this->assertEquals(1, $row->updated_by);
    }

    /** @test */
    public function test_it_only_updates_updated_by_on_subsequent_generations()
    {
        Auth::setUser(new FakeAuditUser(1));
        Sequence::generate('audit_mod', 'AUD', '202601', '{seq}', 3);

        Auth::setUser(new FakeAuditUser(2));
        Sequence::generate('audit_mod', 'AUD', '202601', '{seq}', 3);

        $row = $this->sequencesTable()->where('module', 'audit_mod')->first();

        $this->assertEquals(1, $row->created_by);
        $this->assertEquals(2, $row->updated_by);
    }

    /** @test */
    public function test_it_does_not_stamp_audit_columns_without_an_authenticated_user()
    {
        Sequence::generate('audit_mod', 'AUD', '202601', '{seq}', 3);

        $row = $this->sequencesTable()->where('module', 'audit_mod')->first();

        $this->assertNull($row->created_by);
        $this->assertNull($row->updated_by);
    }

    /** @test */
    public function test_reset_stamps_created_by_and_updated_by_when_creating_a_new_counter()
    {
        Auth::setUser(new FakeAuditUser(1));

        Sequence::reset('audit_reset', 'RST', '202601', 'default', 10);

        $row = $this->sequencesTable()->where('module', 'audit_reset')->first();

        $this->assertEquals(10, $row->current_number);
        $this->assertEquals(1, $row->created_by);
        $this->assertEquals(1, $row->updated_by);
    }

    /** @test */
    public function test_reset_only_updates_updated_by_on_an_existing_counter()
    {
        Auth::setUser(new FakeAuditUser(1));
        Sequence::reset('audit_reset', 'RST', '202601', 'default', 10);

        Auth::setUser(new FakeAuditUser(2));
        Sequence::reset('audit_reset', 'RST', '202601', 'default', 20);

        $row = $this->sequencesTable()->where('module', 'audit_reset')->first();

        $this->assertEquals(20, $row->current_number);
        $this->assertEquals(1, $row->created_by);
        $this->assertEquals(2, $row->updated_by);
    }
}

class FakeAuditUser implements Authenticatable
{
    public function __construct(protected int|string $id) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
