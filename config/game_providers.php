<?php

use App\Services\GameProviders\Adapters\PpSlotProvider;

return [
    'default' => env('GAME_PROVIDER_DEFAULT', 'ppslot'),

    'http' => [
        'connect_timeout' => (int) env('GAME_PROVIDER_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GAME_PROVIDER_TIMEOUT', 15),
        'retries' => (int) env('GAME_PROVIDER_RETRIES', 2),
        'retry_sleep_ms' => (int) env('GAME_PROVIDER_RETRY_SLEEP_MS', 250),
    ],

    'drivers' => [
        'ppslot' => [
            'class' => PpSlotProvider::class,
            'base_url' => env('PPSLOT_API_URL', 'https://game.ppslot.site'),
            'app_id' => env('PPSLOT_APP_ID'),
            'app_secret' => env('PPSLOT_APP_SECRET'),
            'language' => env('PPSLOT_LANGUAGE', 'my'),
            'currency' => env('PPSLOT_CURRENCY', 'MMK'),
            'product_id' => (int) env('PPSLOT_PRODUCT_ID', 0),
            'product_code' => (int) env('PPSLOT_PRODUCT_CODE', 900001),
            'game_type_map' => [
                0 => (int) env('PPSLOT_SLOT_GAME_TYPE_ID', 1),
                1 => (int) env('PPSLOT_FISHING_GAME_TYPE_ID', 2),
                3 => (int) env('PPSLOT_CARD_GAME_TYPE_ID', 3),
                4 => (int) env('PPSLOT_LOTTERY_GAME_TYPE_ID', 4),
            ],
        ],
    ],
];
