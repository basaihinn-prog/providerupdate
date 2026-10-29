<?php

namespace App\Services;

use App\Models\GameInstance;

class GameCreatorService
{
    public function createGameInstance(array $attributes): array
    {
        $defaults = config('slot.defaults', []);
        $gameCode = $attributes['game_code_override'] ?? $this->generateGameCode();
        $rawApiKey = $this->generateApiKey();
        $config = $this->buildConfigPayload($attributes);

        $game = GameInstance::create([
            'provider_name' => $attributes['provider_name'] ?? ($defaults['provider_name'] ?? 'buffalo'),
            'game_code' => $gameCode,
            'display_name' => $attributes['display_name'] ?? ($defaults['display_name'] ?? 'African Buffalo 1024 Ways'),
            'api_key_hash' => hash('sha256', $rawApiKey),
            'api_key_last4' => substr($rawApiKey, -4),
            'rtp_target' => round((float) ($attributes['rtp_target'] ?? ($defaults['default_rtp_target'] ?? 96.5)), 2),
            'volatility' => $attributes['volatility'] ?? 'medium',
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'test_mode' => (bool) ($attributes['test_mode'] ?? false),
            'allow_manual_bonus' => (bool) ($attributes['allow_manual_bonus'] ?? false),
            'config' => $config,
        ]);

        return [
            'game' => $game,
            'api_key' => $rawApiKey,
        ];
    }

    public function ensureDefaultGame(): GameInstance
    {
        $defaults = config('slot.defaults', []);
        $gameCode = $defaults['game_code'] ?? 'BUFFALO1024';
        $existing = GameInstance::where('game_code', $gameCode)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createGameInstance([
            'provider_name' => $defaults['provider_name'] ?? 'buffalo',
            'display_name' => $defaults['display_name'] ?? 'African Buffalo 1024 Ways',
            'rtp_target' => $defaults['default_rtp_target'] ?? 96.5,
            'volatility' => 'medium',
            'is_active' => true,
            'test_mode' => false,
            'allow_manual_bonus' => false,
            'game_code_override' => $gameCode,
        ])['game'];
    }

    public function resolveRequestedGame(?string $requestedGameCode = null): GameInstance
    {
        if ($requestedGameCode !== null && $requestedGameCode !== '') {
            $game = GameInstance::where('game_code', strtoupper($requestedGameCode))->first();

            if ($game !== null) {
                return $game;
            }
        }

        return $this->ensureDefaultGame();
    }

    public function buildRuntimeConfig(GameInstance $game): array
    {
        $defaults = config('slot.defaults', []);
        $gameConfig = $game->config ?? [];
        $merged = array_replace_recursive($defaults, $gameConfig);
        $volatility = $game->volatility ?: 'medium';
        $profiles = $merged['volatility_profiles'] ?? [];
        $activeProfile = $profiles[$volatility] ?? ($profiles['medium'] ?? []);
        $referencesByVolatility = $merged['base_rtp_reference_by_volatility'] ?? [];
        $reference = (float) ($referencesByVolatility[$volatility] ?? ($merged['base_rtp_reference'] ?? 96.5));
        $targetRtp = (float) $game->rtp_target;

        $merged['provider_name'] = $game->provider_name;
        $merged['display_name'] = $game->display_name;
        $merged['game_code'] = $game->game_code;
        $merged['volatility'] = $volatility;
        $merged['base_rtp_reference'] = $reference;
        $merged['rtp_target'] = $targetRtp;
        $merged['payout_scale'] = $reference > 0 ? round($targetRtp / $reference, 8) : 1.0;
        $merged['active_reels'] = $activeProfile['reels'] ?? [];

        return $merged;
    }

    private function buildConfigPayload(array $attributes): array
    {
        $config = $attributes['config'] ?? [];

        return is_array($config) ? $config : [];
    }

    private function generateGameCode(): string
    {
        return 'BUF-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function generateApiKey(): string
    {
        return 'buf_' . bin2hex(random_bytes(16));
    }
}
