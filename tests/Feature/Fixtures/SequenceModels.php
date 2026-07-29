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
