<?php

return [
    // Must match LAUNCH_TICKET_SECRET in the isolated fish1 session service.
    // Keep the secret in the protected environment only; never commit a real value.
    'ticket_secret' => env('FISH_LAUNCH_TICKET_SECRET', ''),
    'issuer' => env('FISH_LAUNCH_TICKET_ISSUER', 'laravel-backend'),
    'audience' => env('FISH_LAUNCH_TICKET_AUDIENCE', 'fish1-session-service'),
    'shop_id' => env('FISH_SHOP_ID', ''),
    // One configured game key prevents callers from selecting arbitrary games.
    'game' => env('FISH_GAME_KEY', ''),
    'ticket_ttl_seconds' => (int) env('FISH_LAUNCH_TICKET_TTL_SECONDS', 90),
];
