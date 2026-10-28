<?php

return [
    /*
     * Server-owned simulation parameters. These are gameplay rules, not RTP overrides.
     * Keep values versioned in source control and review changes before deployment.
     */
    'tick_ms' => (int) env('FISH_ENGINE_TICK_MS', 100),
    'session_ttl_seconds' => (int) env('FISH_ENGINE_SESSION_TTL_SECONDS', 1800),
    'max_command_age_ms' => (int) env('FISH_ENGINE_MAX_COMMAND_AGE_MS', 5000),
    'max_bet_minor' => (int) env('FISH_ENGINE_MAX_BET_MINOR', 100000000),
    'currency' => env('FISH_ENGINE_CURRENCY', 'MMK'),
    'games' => [
        'fish1' => [
            'enabled' => (bool) env('FISH_ENGINE_FISH1_ENABLED', false),
            'version' => 'fish1-v1',
            'width' => 1280,
            'height' => 720,
            'fish' => [
                'small' => ['radius' => 18, 'hp' => 1, 'payout_multiplier' => 2],
                'medium' => ['radius' => 28, 'hp' => 2, 'payout_multiplier' => 5],
                'large' => ['radius' => 42, 'hp' => 4, 'payout_multiplier' => 12],
                'boss' => ['radius' => 64, 'hp' => 10, 'payout_multiplier' => 30],
            ],
            'weapons' => [
                '1' => ['cost_multiplier' => 1],
                '2' => ['cost_multiplier' => 2],
                '3' => ['cost_multiplier' => 3],
                '4' => ['cost_multiplier' => 5],
            ],
        ],
    ],
];
