<?php

namespace App\Services\Games;

use App\Contracts\Games\GameEngine;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Fish1Engine implements GameEngine
{
    public function key(): string
    {
        return 'fish1';
    }

    public function version(): string
    {
        return (string) config('fish_engine.games.fish1.version', 'fish1-v1');
    }

    public function start(int $playerId): array
    {
        $config = config('fish_engine.games.fish1');
        if (! $config || ! ($config['enabled'] ?? false)) {
            throw new InvalidArgumentException('Fish1 engine is disabled.');
        }

        $fish = [];
        $types = array_keys($config['fish']);
        for ($i = 0; $i < 18; $i++) {
            $type = $types[random_int(0, count($types) - 1)];
            $fish[] = $this->spawnFish($type, $config['fish'][$type], $config);
        }

        return [
            'schema_version' => 1,
            'player_id' => $playerId,
            'tick' => 0,
            'rng_commitment' => hash('sha256', random_bytes(32)),
            'fish' => $fish,
            'cannon' => ['x' => (int) ($config['width'] / 2), 'y' => $config['height'] - 24],
            'last_fire_at_ms' => null,
            'rounds' => [],
        ];
    }

    public function tick(array $state, int $elapsedMs): array
    {
        $elapsedMs = max(0, min($elapsedMs, 1000));
        $config = config('fish_engine.games.fish1');
        $seconds = $elapsedMs / 1000;
        $state['tick'] = (int) ($state['tick'] ?? 0) + 1;

        foreach ($state['fish'] as &$fish) {
            if (($fish['alive'] ?? false) !== true) {
                continue;
            }
            $fish['x'] = round((float) $fish['x'] + (float) $fish['vx'] * $seconds, 3);
            $fish['y'] = round((float) $fish['y'] + (float) $fish['vy'] * $seconds, 3);
            if ($fish['x'] < -80 || $fish['x'] > $config['width'] + 80 || $fish['y'] < -80 || $fish['y'] > $config['height'] + 80) {
                $fish['alive'] = false;
            }
        }
        unset($fish);

        $alive = count(array_filter($state['fish'], fn (array $fish) => ($fish['alive'] ?? false) === true));
        if ($alive < 12) {
            $types = array_keys($config['fish']);
            for ($i = 0; $i < 6; $i++) {
                $type = $types[random_int(0, count($types) - 1)];
                $state['fish'][] = $this->spawnFish($type, $config['fish'][$type], $config);
            }
        }

        return $state;
    }

    public function command(array $state, int $playerId, array $command): array
    {
        $config = config('fish_engine.games.fish1');
        $type = (string) ($command['type'] ?? '');
        if ($type !== 'fire') {
            throw new InvalidArgumentException('Unsupported fish1 command.');
        }

        $weapon = (string) ($command['weapon'] ?? '1');
        $weaponRules = $config['weapons'][$weapon] ?? null;
        if (! $weaponRules) {
            throw new InvalidArgumentException('Unknown weapon level.');
        }

        $targetId = (string) ($command['target_fish_id'] ?? '');
        $targetIndex = null;
        foreach ($state['fish'] as $index => $fish) {
            if (($fish['id'] ?? '') === $targetId && ($fish['alive'] ?? false) === true) {
                $targetIndex = $index;
                break;
            }
        }
        if ($targetIndex === null) {
            return ['state' => $state, 'event' => ['type' => 'miss', 'reason' => 'target_not_alive'], 'bet_minor' => null, 'payout_minor' => 0];
        }

        $target = $state['fish'][$targetIndex];
        $weaponCost = (int) ($command['bet_minor'] ?? 0);
        if ($weaponCost < 1 || $weaponCost > (int) config('fish_engine.max_bet_minor', 100000000)) {
            throw new InvalidArgumentException('Invalid stake.');
        }
        $lastFire = $state['last_fire_at_ms'] ?? null;
        $serverNow = (int) floor(microtime(true) * 1000);
        if ($lastFire !== null && ($serverNow - (int) $lastFire) < 80) {
            throw new InvalidArgumentException('Fire rate exceeded.');
        }

        // Target selection alone is not a hit. Server verifies the target is within
        // the server-owned cannon's range; the client cannot submit hit/payout values.
        $cannon = $state['cannon'];
        $dx = (float) $target['x'] - (float) $cannon['x'];
        $dy = (float) $target['y'] - (float) $cannon['y'];
        $distance = sqrt($dx * $dx + $dy * $dy);
        if ($distance > 900) {
            return ['state' => $state, 'event' => ['type' => 'miss', 'reason' => 'out_of_range'], 'bet_minor' => $weaponCost, 'payout_minor' => 0];
        }

        $state['last_fire_at_ms'] = $serverNow;
        $hitChance = min(1.0, max(0.0, ((float) $target['radius'] * (float) $target['radius']) / max(1.0, $distance * $distance) * 24.0));
        $hit = random_int(0, 1_000_000) < (int) floor($hitChance * 1_000_000);
        if (! $hit) {
            return ['state' => $state, 'event' => ['type' => 'miss', 'fish_id' => $targetId], 'bet_minor' => $weaponCost, 'payout_minor' => 0];
        }

        $target['hp'] = (int) $target['hp'] - (int) $weapon;
        if ($target['hp'] > 0) {
            $state['fish'][$targetIndex] = $target;
            return ['state' => $state, 'event' => ['type' => 'hit', 'fish_id' => $targetId, 'hp_remaining' => $target['hp']], 'bet_minor' => $weaponCost, 'payout_minor' => 0];
        }

        $target['alive'] = false;
        $state['fish'][$targetIndex] = $target;
        $multiplier = (int) $target['payout_multiplier'];
        $payout = $weaponCost * $multiplier;
        $roundId = (string) Str::uuid();
        $state['rounds'][] = [
            'round_id' => $roundId,
            'fish_id' => $targetId,
            'fish_type' => $target['type'],
            'bet_minor' => $weaponCost,
            'payout_minor' => $payout,
            'multiplier' => $multiplier,
            'engine_version' => $this->version(),
        ];

        return [
            'state' => $state,
            'event' => ['type' => 'kill', 'fish_id' => $targetId, 'round_id' => $roundId, 'multiplier' => $multiplier],
            'bet_minor' => $weaponCost,
            'payout_minor' => $payout,
        ];
    }

    private function spawnFish(string $type, array $rules, array $config): array
    {
        $fromLeft = random_int(0, 1) === 0;
        $height = (int) $config['height'];

        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'x' => $fromLeft ? -20 : $config['width'] + 20,
            'y' => random_int(80, max(81, $height - 140)),
            'vx' => $fromLeft ? random_int(35, 100) : -random_int(35, 100),
            'vy' => random_int(-25, 25),
            'radius' => (int) $rules['radius'],
            'hp' => (int) $rules['hp'],
            'payout_multiplier' => (int) $rules['payout_multiplier'],
            'alive' => true,
        ];
    }
}
