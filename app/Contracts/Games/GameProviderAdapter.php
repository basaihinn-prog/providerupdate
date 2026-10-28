<?php

namespace App\Contracts\Games;

interface GameProviderAdapter
{
    public function key(): string;

    /** Exchange a provider launch token for a local, verified player/session identity. */
    public function verifyLaunch(array $payload, array $headers): array;

    /** Normalize a provider event into a stable internal event shape. */
    public function normalizeEvent(array $payload, array $headers): array;

    /** Return provider capabilities without assuming all providers share one protocol. */
    public function capabilities(): array;
}
