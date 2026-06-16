<?php

namespace MadeByClowd\Sequenceable\Contracts;

interface Sequenceable
{
    /**
     * Get the sequence configuration for the model.
     *
     * Should return an array with configuration keys:
     * - 'column': Target attribute on model (default 'number')
     * - 'module': Sequence partition module name (default is model's table name)
     * - 'type_code': Static type code string (e.g. 'INV')
     * - 'type_relation': Dynamic type code resolved from model relationship
     * - 'default_type': Default fallback type code if above is missing (default 'GEN')
     * - 'period': Reset period: 'daily', 'weekly', 'monthly', 'yearly', 'never', or custom format/callable (default 'monthly')
     * - 'scope': Column name on model to scope the sequence (e.g. 'tenant_id')
     * - 'format_template': Number styling template (e.g. 'INV-{YYYY}-{seq:5}')
     * - 'pad_length': Counter padding length (default 5)
     *
     * Multiple sequences can also be defined by returning an array of configuration arrays,
     * keyed by the target model column.
     *
     * @return array<string, mixed>
     */
    public function getSequenceConfig(): array;
}
