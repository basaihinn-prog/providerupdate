<?php

namespace App\Services\Slot;

class Ways1024Calculator
{
    public function calculate(
        array $reelDisplay,
        float $betAmount,
        array $runtimeConfig,
        float $featureMultiplier = 1.0
    ): array {
        $effectiveWayDivisor = (float) ($runtimeConfig['effective_way_divisor'] ?? 8);
        $coinBet = $effectiveWayDivisor > 0 ? $betAmount / $effectiveWayDivisor : $betAmount;
        $payoutScale = (float) ($runtimeConfig['payout_scale'] ?? 1.0);
        $symbols = $runtimeConfig['symbols'] ?? [];
        $scatterSymbol = (string) ($runtimeConfig['free_spins']['trigger_symbol'] ?? 'scatter');
        $wildSubstituteReels = array_map('intval', $runtimeConfig['wild_substitute_reels'] ?? [1, 2, 3]);
        $wins = [];
        $auditWins = [];
        $winPatterns = [];
        $baseWin = 0.0;
        $scatterWin = 0.0;
        $parsedGrid = [];

        foreach ($reelDisplay as $reelIndex => $reelSymbols) {
            $parsedGrid[] = [
                'reel' => (int) $reelIndex,
                'symbols' => array_values($reelSymbols),
            ];
        }

        foreach ($symbols as $symbolName => $symbolConfig) {
            if ($symbolName === $scatterSymbol || $symbolName === 'scatter') {
                continue;
            }

            $matchedReels = [];
            $reelMatchCounts = [];
            $waysCount = 1;
            $containsNaturalSymbol = false;

            foreach ($reelDisplay as $reelIndex => $reelSymbols) {
                $positions = [];
                $naturalCount = 0;
                $wildCount = 0;

                foreach ($reelSymbols as $rowIndex => $reelSymbol) {
                    $wildSubstitutes = $reelSymbol === 'wild'
                        && in_array((int) $reelIndex, $wildSubstituteReels, true);
                    $matches = $symbolName === 'wild'
                        ? $reelSymbol === 'wild'
                        : $reelSymbol === $symbolName || $wildSubstitutes;

                    if (! $matches) {
                        continue;
                    }

                    $positions[] = [
                        'reel' => $reelIndex,
                        'row' => $rowIndex,
                        'symbol' => $reelSymbol,
                    ];

                    if ($reelSymbol === $symbolName) {
                        $containsNaturalSymbol = true;
                        $naturalCount++;
                    } elseif ($wildSubstitutes) {
                        $wildCount++;
                    }
                }

                if ($positions === []) {
                    break;
                }

                $matchedReels[] = $positions;
                $reelMatchCounts[] = [
                    'reel' => (int) $reelIndex,
                    'symbol_count' => $naturalCount,
                    'wild_count' => $wildCount,
                    'match_count' => count($positions),
                ];
                $waysCount *= count($positions);
            }

            $matchLength = count($matchedReels);
            $payMultiplier = $symbolConfig['pays'][$matchLength] ?? null;

            if ($payMultiplier === null || $matchLength < 3) {
                continue;
            }

            if ($symbolName !== 'wild' && ! $containsNaturalSymbol) {
                continue;
            }

            $amount = round($coinBet * $payMultiplier * $waysCount * $featureMultiplier * $payoutScale, 2);

            if ($amount <= 0) {
                continue;
            }

            $pattern = $this->buildPattern(
                $matchedReels,
                (int) ($runtimeConfig['grid']['rows'] ?? 4),
                count($reelDisplay)
            );
            $wins[] = [
                'symbol' => $symbolName,
                'kind' => 'ways',
                'match_count' => $matchLength,
                'ways' => $waysCount,
                'multiplier' => round($payMultiplier * $featureMultiplier * $payoutScale, 4),
                'amount' => $amount,
                'positions' => $matchedReels,
            ];
            $winPatterns[] = $pattern;
            $baseWin += $amount;

            $formulaParts = [];
            foreach ($reelMatchCounts as $reelMatchCount) {
                $reelParts = [];
                if ($reelMatchCount['symbol_count'] > 0) {
                    $reelParts[] = $reelMatchCount['symbol_count'] . ' ' . ucfirst($symbolName);
                }
                if ($reelMatchCount['wild_count'] > 0) {
                    $reelParts[] = $reelMatchCount['wild_count'] . ' Wild';
                }
                $formulaParts[] = implode(' + ', $reelParts) . ' on R' . ((int) $reelMatchCount['reel'] + 1);
            }

            $auditWins[] = [
                'symbol' => $symbolName,
                'chain_length' => $matchLength,
                'reel_counts' => $reelMatchCounts,
                'ways' => $waysCount,
                'ways_formula' => implode(' x ', array_column($reelMatchCounts, 'match_count')) . ' = ' . $waysCount . ' ways',
                'path_formula' => implode(' x ', $formulaParts) . ' = ' . $waysCount . ' ways',
                'base_win_value' => (float) $payMultiplier,
                'credit_award' => $amount,
            ];
        }

        $scatterConfig = $symbols[$scatterSymbol] ?? null;
        $scatterCount = $this->countSymbol($reelDisplay, $scatterSymbol);
        $scatterPays = $scatterConfig['pays'] ?? [];
        $scatterTriggerCount = $scatterPays === [] ? 3 : (int) array_key_first($scatterPays);

        if ($scatterConfig !== null) {
            $scatterPay = $scatterConfig['pays'][$scatterCount] ?? null;

            if ($scatterPay !== null) {
                $scatterWin = round($betAmount * $scatterPay * $featureMultiplier * $payoutScale, 2);
                $wins[] = [
                    'symbol' => $scatterSymbol,
                    'kind' => 'scatter',
                    'match_count' => $scatterCount,
                    'ways' => 1,
                    'multiplier' => round($scatterPay * $featureMultiplier * $payoutScale, 4),
                    'amount' => $scatterWin,
                    'positions' => $this->allPositionsForSymbol($reelDisplay, $scatterSymbol),
                ];
                $winPatterns[] = $this->buildPatternFromFlatPositions(
                    $this->flattenPositions($this->allPositionsForSymbol($reelDisplay, $scatterSymbol)),
                    (int) ($runtimeConfig['grid']['rows'] ?? 4),
                    count($reelDisplay)
                );
            }
        }

        return [
            'base_win_amount' => round($baseWin, 2),
            'scatter_win_amount' => round($scatterWin, 2),
            'wins' => $wins,
            'win_patterns' => $winPatterns,
            'scatter_count' => $scatterCount,
            'audit_trail' => [
                'parsed_grid' => $parsedGrid,
                'scatter_counts' => [
                    'symbol' => $scatterSymbol,
                    'count' => $scatterCount,
                    'trigger_count' => $scatterTriggerCount,
                    'triggered' => $scatterCount >= $scatterTriggerCount,
                    'scatter_payout' => round($scatterWin, 2),
                    'free_spin_triggers' => (int) ($runtimeConfig['free_spins']['awards'][$scatterCount] ?? 0),
                ],
                'active_wins' => $auditWins,
                'final_aggregated_credit_win' => round($baseWin + $scatterWin, 2),
            ],
        ];
    }

    public function countSymbol(array $reelDisplay, string $symbol): int
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

    public function allPositionsForSymbol(array $reelDisplay, string $symbol): array
    {
        $positions = [];

        foreach ($reelDisplay as $reelIndex => $reelSymbols) {
            foreach ($reelSymbols as $rowIndex => $reelSymbol) {
                if ($reelSymbol === $symbol) {
                    $positions[] = [
                        'reel' => $reelIndex,
                        'row' => $rowIndex,
                        'symbol' => $reelSymbol,
                    ];
                }
            }
        }

        return [$positions];
    }

    private function buildPattern(array $matchedReels, int $rows, int $reelCount): array
    {
        $flatPositions = $this->flattenPositions($matchedReels);

        return $this->buildPatternFromFlatPositions($flatPositions, $rows, $reelCount);
    }

    private function buildPatternFromFlatPositions(array $flatPositions, int $rows, int $reelCount): array
    {
        $pattern = array_fill(0, $rows, array_fill(0, $reelCount, 0));

        foreach ($flatPositions as $position) {
            $pattern[$position['row']][$position['reel']] = 1;
        }

        return $pattern;
    }

    private function flattenPositions(array $matchedReels): array
    {
        $flatPositions = [];

        foreach ($matchedReels as $positions) {
            foreach ($positions as $position) {
                $flatPositions[] = $position;
            }
        }

        return $flatPositions;
    }
}
