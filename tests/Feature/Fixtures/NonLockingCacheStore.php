<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Fixtures;

use Illuminate\Contracts\Cache\Store;

/**
 * A minimal cache store that intentionally does NOT implement LockProvider,
 * used to test the "store does not support atomic locks" guard clauses.
 */
class NonLockingCacheStore implements Store
{
    public function get($key)
    {
        return null;
    }

    public function many(array $keys)
    {
        return array_fill_keys($keys, null);
    }

    public function put($key, $value, $seconds)
    {
        return true;
    }

    public function putMany(array $values, $seconds)
    {
        return true;
    }

    public function touch($key, $seconds)
    {
        return true;
    }

    public function increment($key, $value = 1)
    {
        return false;
    }

    public function decrement($key, $value = 1)
    {
        return false;
    }

    public function forever($key, $value)
    {
        return true;
    }

    public function forget($key)
    {
        return true;
    }

    public function flush()
    {
        return true;
    }

    public function getPrefix()
    {
        return '';
    }
}
