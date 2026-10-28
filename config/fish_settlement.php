<?php

return [
    // Shared only between providerupdate and the trusted fish game/session service.
    // Never expose this secret to browser code.
    'secret' => env('FISH_SETTLEMENT_SECRET', ''),
    'signature_ttl_seconds' => (int) env('FISH_SETTLEMENT_SIGNATURE_TTL_SECONDS', 300),
];
