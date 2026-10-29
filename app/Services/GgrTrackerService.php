<?php

namespace App\Services;

use App\Models\GameInstance;
use App\Models\GameMetric;

class GgrTrackerService
{
    public function recordSpin(GameInstance $game, float $betAmount, float $totalWinAmount, bool $isHit): GameMetric
    {
        $metric = GameMetric::firstOrCreate(
            ['game_instance_id' => $game->id],
            [
                'spin_count' => 0,
                'hit_count' => 0,
                'total_bet' => 0,
                'total_win' => 0,
                'ggr' => 0,
                'observed_rtp' => 0,
                'meta' => [],
                'last_spin_at' => now(),
            ]
        );

        $metric->spin_count += 1;
        $metric->hit_count += $isHit ? 1 : 0;
        $metric->total_bet = round((float) $metric->total_bet + $betAmount, 2);
        $metric->total_win = round((float) $metric->total_win + $totalWinAmount, 2);
        $metric->ggr = round((float) $metric->total_bet - (float) $metric->total_win, 2);
        $metric->observed_rtp = $metric->total_bet > 0
            ? round(((float) $metric->total_win / (float) $metric->total_bet) * 100, 4)
            : 0;
        $metric->last_spin_at = now();
        $metric->save();

        return $metric;
    }
}
