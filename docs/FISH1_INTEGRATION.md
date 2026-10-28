# fish1 launch-ticket integration

This adds a dedicated Laravel ticket endpoint for the isolated fish1 session service. It does not reuse Buffalo routes or the PPSLOT callback protocol.

## Endpoint

`POST /api/fish/launch-ticket` (protected by `auth:sanctum`).

Laravel issues a short-lived HS256 JWT containing `iss`, `aud`, `sub`, `shop_id`, `game`, `jti`, `iat`, `nbf`, and `exp`. The endpoint is fail-closed: it returns HTTP 503 until all required settings are present. The ticket does not contain a wallet balance.

## Protected environment settings

- `FISH_LAUNCH_TICKET_SECRET`: random secret of at least 32 bytes; must exactly match fish1 `LAUNCH_TICKET_SECRET`.
- `FISH_LAUNCH_TICKET_ISSUER=laravel-backend`
- `FISH_LAUNCH_TICKET_AUDIENCE=fish1-session-service`
- `FISH_SHOP_ID`: authorized shop/operator identifier.
- `FISH_GAME_KEY`: fixed game identifier permitted for this deployment.
- `FISH_LAUNCH_TICKET_TTL_SECONDS=90` (capped at 120 seconds).
- `FISH_SESSION_ENDPOINT`: private session-service URL, if needed by the trusted caller.

After changing environment configuration, clear/rebuild Laravel config cache using the normal deployment procedure.

## Integration boundary

This endpoint establishes authenticated, one-time session-ticket issuance. The existing fish1 session service consumes the ticket and creates a PostgreSQL session. It does not implement the Cocos client's full legacy gameplay API, real-time fish simulation, or authoritative bet/win wallet settlement. Do not remove the old demo compatibility layer or route production traffic until the client API contract has been mapped and those missing services are implemented and tested. Do not use Buffalo endpoints or PPSLOT callback endpoints as a substitute.
