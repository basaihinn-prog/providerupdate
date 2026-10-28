<?php

namespace App\Services\Games;

use App\Contracts\Games\GameEngine;
use InvalidArgumentException;

class GameEngineRegistry
{
    /** @param iterable<GameEngine> $engines */
    public function __construct(private iterable $engines)
    {
    }

    public function get(string $key): GameEngine
    {
        foreach ($this->engines as $engine) {
            if ($engine->key() === $key) {
                return $engine;
            }
        }

        throw new InvalidArgumentException("Game engine [{$key}] is not registered.");
    }
}
