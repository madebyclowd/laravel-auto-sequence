<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MadeByClowd\AutoSequence\Contracts\Sequenceable;
use MadeByClowd\AutoSequence\Traits\HasSequenceNumber;

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

class TestStartInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_start'];

    protected $table = 'test_start_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_start' => [
                'module' => 'adv_start',
                'type_code' => 'ST',
                'start_value' => 1000,
                'period' => 'never',
                'format_template' => 'ST-{seq}',
            ],
        ];
    }
}

class TestStepInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_step'];

    protected $table = 'test_step_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_step' => [
                'module' => 'adv_step',
                'type_code' => 'SP',
                'step' => 2,
                'period' => 'never',
                'format_template' => 'SP-{seq}',
            ],
        ];
    }
}

class TestMaxInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_max'];

    protected $table = 'test_max_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_max' => [
                'module' => 'adv_max',
                'type_code' => 'MX',
                'max_value' => 3,
                'period' => 'never',
                'format_template' => 'MX-{seq}',
            ],
        ];
    }
}

class TestExhaustionInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_exhaustion'];

    protected $table = 'test_exhaustion_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_exhaustion' => [
                'module' => 'adv_exhaustion',
                'type_code' => 'EX',
                'max_value' => 10,
                'exhaustion_threshold' => 50,
                'period' => 'never',
                'format_template' => 'EX-{seq}',
            ],
        ];
    }
}

class TestNoManualInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_no_manual'];

    protected $table = 'test_no_manual_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_no_manual' => [
                'module' => 'adv_no_manual',
                'type_code' => 'NM',
                'allow_manual' => false,
                'period' => 'never',
                'format_template' => 'NM-{seq}',
            ],
        ];
    }
}

class TestClosureInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_closure'];

    protected $table = 'test_closure_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_closure' => [
                'module' => 'adv_closure',
                'type_code' => 'CL',
                'period' => 'never',
                'format_template' => function ($model) {
                    return 'CL-'.($model->id ? 'EXIST' : 'NEW').'-{seq}';
                },
            ],
        ];
    }
}

class TestRelationDotInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_relation_dot', 'branch_id'];

    protected $table = 'test_relation_dot_invoices';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(TestBranch::class);
    }

    public function getSequenceConfig(): array
    {
        return [
            'seq_relation_dot' => [
                'module' => 'adv_relation_dot',
                'type_code' => 'RD',
                'period' => 'never',
                'format_template' => 'RD-{attribute:branch.code}-{seq}',
            ],
        ];
    }
}

class TestContinuousInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_continuous'];

    protected $table = 'test_continuous_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_continuous' => [
                'module' => 'adv_continuous',
                'type_code' => 'CN',
                'continuous' => true,
                'period' => 'never',
                'format_template' => 'CN-{seq}',
            ],
        ];
    }
}

class TestSoftDeleteInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['seq_soft'];

    protected $table = 'test_soft_delete_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_soft' => [
                'module' => 'adv_soft',
                'type_code' => 'SD',
                'continuous' => true,
                'period' => 'never',
                'format_template' => 'SD-{seq}',
            ],
        ];
    }
}

class TestContinuousMaxInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_continuous_max'];

    protected $table = 'test_continuous_max_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_continuous_max' => [
                'module' => 'adv_continuous_max',
                'type_code' => 'CM',
                'continuous' => true,
                'max_value' => 2,
                'period' => 'never',
                'format_template' => 'CM-{seq}',
            ],
        ];
    }
}

/**
 * Uses the flat/shorthand config format (a single config array, not
 * keyed by column name) and is also continuous, to exercise the
 * normalization branch in both the `creating` and `deleted` listeners.
 */
class TestShorthandInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['number'];

    protected $table = 'test_shorthand_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'module' => 'shorthand',
            'type_code' => 'SH',
            'continuous' => true,
            'period' => 'never',
            'format_template' => 'SH-{seq:3}',
        ];
    }
}

/**
 * Uses the HasSequenceNumber trait without implementing the Sequenceable
 * contract, to exercise the trait's early-return guard clauses.
 */
class TestPlainTraitInvoice extends Model
{
    use HasSequenceNumber;

    protected $fillable = ['number'];

    protected $table = 'test_plain_trait_invoices';
}

class TestRelationArrayInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_relation_array', 'branch_id'];

    protected $table = 'test_relation_array_invoices';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(TestBranch::class);
    }

    public function getSequenceConfig(): array
    {
        return [
            'seq_relation_array' => [
                'module' => 'adv_relation_array',
                'type_relation' => [
                    'relation' => 'branch',
                    'column' => 'code',
                ],
                'default_type' => 'HQ',
                'period' => 'never',
                'format_template' => '{type_code}-{seq:3}',
            ],
        ];
    }
}

class TestDefaultTypeInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_default_type'];

    protected $table = 'test_default_type_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_default_type' => [
                'module' => 'adv_default_type',
                'period' => 'never',
                'format_template' => '{type_code}-{seq:3}',
            ],
        ];
    }
}

/**
 * Resolves the partition period via a custom resolver class string
 * (`app($class)->resolve($model)`), instead of a built-in keyword or closure.
 */
class FiscalPeriodResolver
{
    public function resolve(Model $model): string
    {
        return 'FY'.($model->created_at?->year ?? now()->year);
    }
}

class TestCustomPeriodInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_custom_period'];

    protected $table = 'test_custom_period_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_custom_period' => [
                'module' => 'adv_custom_period',
                'type_code' => 'CP',
                'period' => FiscalPeriodResolver::class,
                'format_template' => '{type_code}-{period}-{seq:3}',
            ],
        ];
    }
}

class TestPeriodVariantInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_daily', 'seq_weekly'];

    protected $table = 'test_period_variant_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_daily' => [
                'module' => 'adv_daily',
                'type_code' => 'DL',
                'period' => 'daily',
                'format_template' => '{type_code}-{period}-{seq:3}',
            ],
            'seq_weekly' => [
                'module' => 'adv_weekly',
                'type_code' => 'WK',
                'period' => 'weekly',
                'format_template' => '{type_code}-{period}-{seq:3}',
            ],
        ];
    }
}

class TestScopeClosureInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_scope_closure'];

    protected $table = 'test_scope_closure_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_scope_closure' => [
                'module' => 'adv_scope_closure',
                'type_code' => 'SC',
                'period' => 'never',
                'scope' => function ($model) {
                    return 'closure-scope';
                },
                'format_template' => '{type_code}-{seq:3}',
            ],
        ];
    }
}

/**
 * Resolves the scope partition via a custom resolver class string
 * (`app($class)->resolve($model)`), instead of a built-in attribute name.
 */
class RegionScopeResolver
{
    public function resolve(Model $model): string
    {
        return 'region-resolved';
    }
}

class TestScopeClassInvoice extends Model implements Sequenceable
{
    use HasSequenceNumber;

    protected $fillable = ['seq_scope_class'];

    protected $table = 'test_scope_class_invoices';

    public function getSequenceConfig(): array
    {
        return [
            'seq_scope_class' => [
                'module' => 'adv_scope_class',
                'type_code' => 'RS',
                'period' => 'never',
                'scope' => RegionScopeResolver::class,
                'format_template' => '{type_code}-{seq:3}',
            ],
        ];
    }
}
