<?php

namespace App\Services\GameProviders\Adapters;

use App\Models\GameList;
use App\Models\User;
use App\Services\GameProviders\Contracts\GameProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PpSlotProvider implements GameProviderInterface
{
    private function client(): PendingRequest
    {
        $appId = (string) config('game_providers.drivers.ppslot.app_id');
        $appSecret = (string) config('game_providers.drivers.ppslot.app_secret');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('PPSLOT AppID/AppSecret are not configured.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'AppID' => $appId,
                'AppSecret' => $appSecret,
            ])
            ->connectTimeout((int) config('game_providers.http.connect_timeout', 5))
            ->timeout((int) config('game_providers.http.timeout', 15))
            ->retry(
                (int) config('game_providers.http.retries', 2),
                (int) config('game_providers.http.retry_sleep_ms', 250),
                throw: false
            );
    }

    private function endpoint(string $path): string
    {
        $baseUrl = rtrim((string) config('game_providers.drivers.ppslot.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('PPSLOT base URL is not configured.');
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    public function launch(User $user, GameList $game, array $options = []): array
    {
        $response = $this->client()->post($this->endpoint('/api/v1/game/launch'), [
            'UserID' => (string) $user->user_name,
            'GameID' => (string) $game->game_code,
            'Language' => (string) ($options['language'] ?? config('game_providers.drivers.ppslot.language', 'my')),
            'Platform' => (string) ($options['platform'] ?? 'mobile'),
        ]);

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('PPSLOT returned a non-JSON launch response.');
        }

        if (! $response->successful() || (int) ($body['code'] ?? 0) !== 0 || empty($body['data']['Url'])) {
            throw new RuntimeException((string) ($body['error'] ?? 'PPSLOT game launch failed.'));
        }

        return [
            'url' => (string) $body['data']['Url'],
            'content' => null,
            'provider_response' => $body,
        ];
    }

    public function games(string $language = 'en'): array
    {
        $response = $this->client()->post($this->endpoint('/api/v1/game/list'), [
            'Language' => $language,
        ]);

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('PPSLOT returned a non-JSON game list response.');
        }

        if (! $response->successful() || (int) ($body['code'] ?? 0) !== 0) {
            throw new RuntimeException((string) ($body['error'] ?? 'PPSLOT game list request failed.'));
        }

        $games = $body['data']['List'] ?? [];

        if (! is_array($games)) {
            throw new RuntimeException('PPSLOT game list payload is invalid.');
        }

        return $games;
    }
}
