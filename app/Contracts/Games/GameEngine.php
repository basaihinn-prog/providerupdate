<?php

namespace App\Contracts\Games;

interface GameEngine
{
    public function key(): string;

    public function version(): string;

    /** Create server-owned state for a newly started session. */
    public function start(int $playerId): array;

    /** Advance simulation using elapsed server time, never client-supplied positions. */
    public function tick(array $state, int $elapsedMs): array;

    /** Validate and resolve a player command against server-owned state. */
    public function command(array $state, int $playerId, array $command): array;
}
