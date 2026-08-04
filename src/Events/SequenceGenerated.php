<?php

namespace MadeByClowd\AutoSequence\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class SequenceGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly string $module,
        public readonly string $typeCode,
        public readonly string $period,
        public readonly string $scope,
        public readonly string $number,
        public readonly ?Model $model = null
    ) {}
}
