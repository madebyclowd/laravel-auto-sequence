<?php

namespace MadeByClowd\AutoSequence\Exceptions;

class SequenceLockException extends AutoSequenceException
{
    /**
     * Create a new lock exception.
     */
    public static function lockAcquisitionFailed(string $key, int $timeout, ?\Throwable $previous = null): self
    {
        return new self("Failed to acquire lock for sequence key '{$key}' within {$timeout} seconds.", 0, $previous);
    }
}
