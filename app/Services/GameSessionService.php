<?php

namespace App\Services;

use App\Models\GameInstance;
use App\Models\SlotGameSession;
use App\Models\User;

class GameSessionService
{
    public function resolvePlayer(?int $playerId = null, ?string $playerName = null, ?User $fallbackUser = null): ?User
    {
        if ($fallbackUser !== null) {
            return $fallbackUser;
        }

        if ($playerId !== null) {
            return User::find($playerId);
        }

        if ($playerName !== null && $playerName !== '') {
            return User::where('name', $playerName)->first();
        }

        return null;
    }

    public function initSession(
        GameInstance $game,
        string $playerIdentifier,
        ?User $user = null,
        ?string $clientSeed = null,
        array $meta = []
    ): SlotGameSession {
        $query = SlotGameSession::where('game_instance_id', $game->id)
            ->where('status', 'active')
            ->where('player_identifier', $playerIdentifier);

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        $session = $query->latest()->first();

        if ($session !== null) {
            $session->client_seed = $clientSeed ?: $session->client_seed;
            $session->meta = array_merge($session->meta ?? [], $meta);
            $session->last_activity_at = now();
            $session->save();

            return $session;
        }

        return SlotGameSession::create([
            'game_instance_id' => $game->id,
            'user_id' => $user?->id,
            'session_token' => bin2hex(random_bytes(24)),
            'player_identifier' => $playerIdentifier,
            'status' => 'active',
            'client_seed' => $clientSeed,
            'free_spins_remaining' => 0,
            'free_spins_awarded' => 0,
            'free_spin_multiplier' => 1,
            'total_bet' => 0,
            'total_win' => 0,
            'ggr' => 0,
            'meta' => $meta,
            'last_activity_at' => now(),
        ]);
    }

    public function getSessionByToken(GameInstance $game, string $sessionToken): SlotGameSession
    {
        return SlotGameSession::where('game_instance_id', $game->id)
            ->where('session_token', $sessionToken)
            ->firstOrFail();
    }

    public function queueManualBonus(
        SlotGameSession $session,
        string $feature,
        int $quantity = 0,
        ?float $multiplier = null
    ): SlotGameSession {
        $meta = $session->meta ?? [];

        if ($feature === 'free_spins') {
            $session->free_spins_remaining += max(1, $quantity);
            $session->free_spins_awarded += max(1, $quantity);
            $session->free_spin_multiplier = max((float) $session->free_spin_multiplier, (float) ($multiplier ?? 2));
        }

        if ($feature === 'coin_bonus') {
            $meta['manual_coin_bonus_multiplier'] = $multiplier ?? max(1, $quantity);
            $session->meta = $meta;
        }

        $session->last_activity_at = now();
        $session->save();

        return $session->refresh();
    }

    public function closeSession(SlotGameSession $session): void
    {
        $session->status = 'closed';
        $session->last_activity_at = now();
        $session->save();
    }
}
