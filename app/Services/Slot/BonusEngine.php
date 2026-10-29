<?php

namespace App\Services\Slot;

class BonusEngine
{
    public function evaluate(
        array $reelDisplay,
        float $betAmount,
        array $runtimeConfig,
        CryptoRng $rng,
        array $sessionState
    ): array {
        $payoutScale = (float) ($runtimeConfig['payout_scale'] ?? 1.0);
        $isFreeSpin = (bool) ($sessionState['is_free_spin'] ?? false);
        $featureMultiplier = $isFreeSpin ? (float) ($sessionState['free_spin_multiplier'] ?? 1.0) : 1.0;
        $scatterCount = $this->countSymbol($reelDisplay, $runtimeConfig['free_spins']['trigger_symbol'] ?? 'scatter');
        $freeSpinAwards = $runtimeConfig['free_spins']['awards'] ?? [];
        $freeSpinMultipliers = $runtimeConfig['free_spins']['multiplier_by_trigger'] ?? [];
        $freeSpinsAwarded = (int) ($freeSpinAwards[$scatterCount] ?? 0);
        $freeSpinMultiplier = (float) ($freeSpinMultipliers[$scatterCount] ?? ($sessionState['free_spin_multiplier'] ?? 1.0));

        $coinBonusConfig = $runtimeConfig['coin_bonus'] ?? [];
        $coinCount = $this->countSymbol($reelDisplay, $coinBonusConfig['trigger_symbol'] ?? 'coin');
        $bonusWinAmount = 0.0;
        $bonusEvents = [];
        $manualBonusConsumed = false;
        $manualMultiplier = $sessionState['manual_coin_bonus_multiplier'] ?? null;

        if ($coinCount >= (int) ($coinBonusConfig['min_count'] ?? PHP_INT_MAX)) {
            $selected = $coinBonusConfig['weighted_multipliers'][$rng->weightedIndex($coinBonusConfig['weighted_multipliers'])];
            $amount = round($betAmount * (float) $selected['multiplier'] * $featureMultiplier * $payoutScale, 2);
            $bonusWinAmount += $amount;
            $bonusEvents[] = [
                'type' => 'coin_bonus',
                'trigger_count' => $coinCount,
                'multiplier' => (float) $selected['multiplier'],
                'amount' => $amount,
            ];
        }

        if ($manualMultiplier !== null) {
            $amount = round($betAmount * (float) $manualMultiplier * $featureMultiplier * $payoutScale, 2);
            $bonusWinAmount += $amount;
            $bonusEvents[] = [
                'type' => 'manual_coin_bonus',
                'trigger_count' => 0,
                'multiplier' => (float) $manualMultiplier,
                'amount' => $amount,
            ];
            $manualBonusConsumed = true;
        }

        return [
            'free_spins_awarded' => $freeSpinsAwarded,
            'free_spin_multiplier' => $freeSpinMultiplier,
            'coin_count' => $coinCount,
            'bonus_win_amount' => round($bonusWinAmount, 2),
            'bonus_events' => $bonusEvents,
            'manual_bonus_consumed' => $manualBonusConsumed,
        ];
    }

    private function countSymbol(array $reelDisplay, string $symbol): int
    {
        $count = 0;

        foreach ($reelDisplay as $reelSymbols) {
            foreach ($reelSymbols as $reelSymbol) {
                if ($reelSymbol === $symbol) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
