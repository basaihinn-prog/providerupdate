<?php

namespace App\Services\GameProviders;

use App\Models\GameList;
use App\Services\GameProviders\Contracts\GameProviderInterface;
use InvalidArgumentException;

class ProviderManager
{
    public function driver(string $provider): GameProviderInterface
    {
        $key = strtolower(trim($provider));
        $drivers = config('game_providers.drivers', []);
        $driverConfig = $drivers[$key] ?? null;

        if (! $driverConfig || empty($driverConfig['class'])) {
            throw new InvalidArgumentException("Game provider driver [{$provider}] is not configured.");
        }

        $class = $driverConfig['class'];
        $driver = app($class);

        if (! $driver instanceof GameProviderInterface) {
            throw new InvalidArgumentException("Game provider driver [{$class}] must implement GameProviderInterface.");
        }

        return $driver;
    }

    public function forGame(GameList $game): GameProviderInterface
    {
        $provider = $game->product?->provider ?: $game->provider ?: config('game_providers.default');

        return $this->driver((string) $provider);
    }
}
