<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Fixtures;

use Illuminate\Cache\ArrayStore;

/**
 * An array-backed cache store whose very first `get()` call always misses,
 * regardless of what is actually stored — simulating the moment right
 * before a process acquires the pre-allocation refill lock. Every call
 * after that behaves like a normal array store. Used to exercise the
 * "double check the cache after acquiring the lock" race-condition guard.
 */
class RaceConditionCacheStore extends ArrayStore
{
    protected int $getCalls = 0;

    public function get($key)
    {
        $this->getCalls++;

        if ($this->getCalls === 1) {
            return null;
        }

        return parent::get($key);
    }
}
