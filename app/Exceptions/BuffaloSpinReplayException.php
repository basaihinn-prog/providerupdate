<?php

namespace App\Exceptions;

use RuntimeException;

class BuffaloSpinReplayException extends RuntimeException
{
    public function __construct(public readonly array $legacyResult)
    {
        parent::__construct('Buffalo spin request was already settled; replaying the original result.');
    }
}
