<?php

namespace MadeByClowd\Sequenceable\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string generate(string $module, string $typeCode, ?string $period = null, ?string $formatTemplate = null, int $padLength = 5, string $scope = 'default', ?\Illuminate\Database\Eloquent\Model $model = null)
 * @method static int getCurrent(string $module, string $typeCode, ?string $period = null, string $scope = 'default')
 * @method static void reset(string $module, string $typeCode, ?string $period = null, string $scope = 'default', int $resetTo = 0)
 *
 * @see \MadeByClowd\Sequenceable\SequenceManager
 */
class Sequence extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'sequenceable';
    }
}
