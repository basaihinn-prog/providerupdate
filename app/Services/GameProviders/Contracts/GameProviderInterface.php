<?php

namespace App\Services\GameProviders\Contracts;

use App\Models\GameList;
use App\Models\User;

interface GameProviderInterface
{
    /**
     * Launch a game and return a normalized provider response.
     *
     * @return array{url:?string,content:?string,provider_response:array}
     */
    public function launch(User $user, GameList $game, array $options = []): array;

    /**
     * Fetch the provider's canonical game catalog.
     *
     * @return array<int,array<string,mixed>>
     */
    public function games(string $language = 'en'): array;
}
