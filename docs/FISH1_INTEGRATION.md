# fish1 launch-ticket integration

## Production domain mapping (cocod.xyz)

- Fish1 Cocos2d-JS frontend: `https://fish.cocod.xyz`
- providerupdate Laravel API: `https://api.cocod.xyz`
- Configure the fish1 bridge as `PROVIDERUPDATE_API_BASE_URL=https://api.cocod.xyz`. This value is the origin only: do not append `/api`, because the bridge adds `/api/fish/launch-ticket` itself.
- The fish session service and launch bridge remain private on the fish1 host at `127.0.0.1:8945` and `127.0.0.1:8946`. Only the fish host's Nginx route `/integration/` is public.
- DNS A records for `fish.cocod.xyz` and `api.cocod.xyz` must point to the correct public IP for the VPS hosting each app. If both apps share one VPS, they may point to the same IP and Nginx selects by hostname. Configure HTTPS certificates for both names.

## Endpoint

`POST /api/fish/launch-ticket` (protected by `auth:sanctum`).

Laravel issues a short-lived HS256 JWT containing `iss`, `aud`, `sub`, `shop_id`, `game`, `jti`, `iat`, `nbf`, and `exp`. The endpoint is fail-closed: it returns HTTP 503 until all required settings are present. The ticket does not contain a wallet balance.

## Protected environment settings

- `FISH_LAUNCH_TICKET_SECRET`: random secret of at least 32 bytes; must exactly match fish1 `LAUNCH_TICKET_SECRET`.
- `FISH_LAUNCH_TICKET_ISSUER=laravel-backend`
- `FISH_LAUNCH_TICKET_AUDIENCE=fish1-session-service`
- `FISH_SHOP_ID`: the real operator/shop identifier from the providerupdate configuration.
- `FISH_GAME_KEY`: the registered fish game key.
- `FISH_LAUNCH_TICKET_TTL_SECONDS=90` (capped at 120 seconds).

Do not guess the shop ID or game key. Keep secrets in protected server environment files; never commit them or put them in browser JavaScript. After changing environment configuration, clear/rebuild Laravel config cache using the normal deployment procedure.

## fish1 launch bridge

The companion fish1 branch adds a private launch bridge: an authenticated player portal passes its Sanctum bearer token to the fish launch page using origin-allowlisted `postMessage`; the bridge forwards that token to this endpoint and consumes the returned one-time ticket through the private PostgreSQL session service. The ticket is not exposed to the browser. See [fish1 PR #4](https://github.com/basaihinn-prog/fish1/pull/4) for the current bridge implementation and environment contract.

## Integration boundary

This endpoint establishes authenticated, one-time session-ticket issuance. The existing fish1 session service consumes the ticket and creates a PostgreSQL session. It does not implement the Cocos client's full legacy gameplay API, real-time fish simulation, or authoritative bet/win wallet settlement. Do not remove the old demo compatibility layer or route production traffic until the client API contract has been mapped and those missing services are implemented and tested. Do not use Buffalo endpoints or PPSLOT callback endpoints as a substitute.
