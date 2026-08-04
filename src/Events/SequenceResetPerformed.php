<?php

namespace MadeByClowd\AutoSequence\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SequenceResetPerformed
{
    use Dispatchable;

    public function __construct(
        public readonly string $module,
        public readonly string $typeCode,
        public readonly string $period,
        public readonly string $scope,
        public readonly int $resetTo
    ) {}
}
