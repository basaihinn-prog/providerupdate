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

After changing environment configuration, clear/rebuild Laravel config cache using the normal deployment procedure.

## fish1 launch bridge

The companion fish1 branch adds a private launch bridge: an authenticated player portal passes its Sanctum bearer token to the fish launch page using origin-allowlisted `postMessage`; the bridge forwards that token to this endpoint and submits the returned one-time ticket to the private PostgreSQL session service. The ticket is not exposed to the browser. See [fish1 PR #4](https://github.com/basaihinn-prog/fish1/pull/4) for the current bridge implementation and environment contract.

## Integration boundary

This endpoint establishes authenticated, one-time session-ticket issuance. The existing fish1 session service consumes the ticket and creates a PostgreSQL session. It does not implement the Cocos client's full legacy gameplay API, real-time fish simulation, or authoritative bet/win wallet settlement. Do not remove the old demo compatibility layer or route production traffic until the client API contract has been mapped and those missing services are implemented and tested. Do not use Buffalo endpoints or PPSLOT callback endpoints as a substitute.
