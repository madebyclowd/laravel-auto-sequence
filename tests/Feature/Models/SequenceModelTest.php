<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MadeByClowd\AutoSequence\Models\Sequence;
use MadeByClowd\AutoSequence\Tests\TestCase;

class SequenceModelTest extends TestCase
{
    /** @test */
    public function test_get_key_returns_the_composite_key_as_an_array()
    {
        $sequence = new Sequence([
            'module' => 'invoice',
            'type_code' => 'INV',
            'period' => '202601',
            'scope' => 'default',
        ]);

        $this->assertSame([
            'module' => 'invoice',
            'type_code' => 'INV',
            'period' => '202601',
            'scope' => 'default',
        ], $sequence->getKey());
    }

    /** @test */
    public function test_get_key_name_returns_the_first_composite_key_field()
    {
        $this->assertSame('module', (new Sequence)->getKeyName());
    }

    /** @test */
    public function test_creator_relation_is_null_when_audit_is_disabled()
    {
        config(['auto-sequence.audit.enabled' => false]);

        $this->assertNull((new Sequence)->creator());
    }

    /** @test */
    public function test_updater_relation_is_null_when_audit_is_disabled()
    {
        config(['auto-sequence.audit.enabled' => false]);

        $this->assertNull((new Sequence)->updater());
    }

    /** @test */
    public function test_creator_relation_uses_configured_audit_columns_when_enabled()
    {
        config([
            'auto-sequence.audit.enabled' => true,
            'auto-sequence.audit.user_model' => SequenceModelTestUser::class,
            'auto-sequence.audit.created_by_column' => 'created_by',
        ]);

        $relation = (new Sequence(['created_by' => 5]))->creator();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('created_by', $relation->getForeignKeyName());
        $this->assertInstanceOf(SequenceModelTestUser::class, $relation->getRelated());
    }

    /** @test */
    public function test_updater_relation_uses_configured_audit_columns_when_enabled()
    {
        config([
            'auto-sequence.audit.enabled' => true,
            'auto-sequence.audit.user_model' => SequenceModelTestUser::class,
            'auto-sequence.audit.updated_by_column' => 'updated_by',
        ]);

        $relation = (new Sequence(['updated_by' => 7]))->updater();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('updated_by', $relation->getForeignKeyName());
        $this->assertInstanceOf(SequenceModelTestUser::class, $relation->getRelated());
    }
}

class SequenceModelTestUser extends Model
{
    protected $table = 'sequence_model_test_users';
}
