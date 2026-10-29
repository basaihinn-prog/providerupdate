<?php

namespace App\Services\Slot;

class ReelStripGenerator
{
    public function generate(array $reels, CryptoRng $rng, int $rows, array $wildPlacement = []): array
    {
        $reelDisplay = [];
        $rowDisplay = array_fill(0, $rows, []);
        $stops = [];
        $excludedReels = array_map('intval', $wildPlacement['excluded_reels'] ?? []);
        $requiredReels = array_map('intval', $wildPlacement['required_reels'] ?? []);

        foreach ($reels as $reelIndex => $strip) {
            if ($strip === []) {
                throw new \InvalidArgumentException('Reel strip cannot be empty.');
            }

            $stopCandidates = range(0, count($strip) - 1);
            if (in_array($reelIndex, $excludedReels, true) || in_array($reelIndex, $requiredReels, true)) {
                $stopCandidates = array_values(array_filter(
                    $stopCandidates,
                    static function (int $candidateStop) use ($strip, $rows, $reelIndex, $excludedReels): bool {
                        $wildIsVisible = false;
                        for ($row = 0; $row < $rows; $row++) {
                            if ($strip[($candidateStop + $row) % count($strip)] === 'wild') {
                                $wildIsVisible = true;
                                break;
                            }
                        }

                        return in_array($reelIndex, $excludedReels, true)
                            ? !$wildIsVisible
                            : $wildIsVisible;
                    }
                ));
            }

            if ($stopCandidates === []) {
                throw new \InvalidArgumentException('Reel strip cannot satisfy the configured wild placement rules.');
            }

            $stop = $stopCandidates[$rng->int(0, count($stopCandidates) - 1)];
            $stops[] = $stop;
            $reelDisplay[$reelIndex] = [];

            for ($row = 0; $row < $rows; $row++) {
                $symbol = $strip[($stop + $row) % count($strip)];
                $reelDisplay[$reelIndex][$row] = $symbol;
                $rowDisplay[$row][$reelIndex] = $symbol;
            }
        }

        return [
            'reels' => $reelDisplay,
            'rows' => $rowDisplay,
            'stops' => $stops,
        ];
    }
}
