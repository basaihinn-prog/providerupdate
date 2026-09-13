# PPSLOT / updatesky Production Integration

`providerupdate` uses PPSLOT / updatesky as its only external game provider. The Laravel application remains the merchant/player backend and the authoritative seamless-wallet balance store. Provider-specific requests are isolated behind `GameProviderInterface` and `PpSlotProvider`.

## Required environment

```env
APP_URL=https://ppslot.site

GAME_PROVIDER_DEFAULT=ppslot
GAME_PROVIDER_CONNECT_TIMEOUT=5
GAME_PROVIDER_TIMEOUT=15
GAME_PROVIDER_RETRIES=2
GAME_PROVIDER_RETRY_SLEEP_MS=250

PPSLOT_API_URL=https://game.ppslot.site
PPSLOT_APP_ID=YOUR_MERCHANT_APP_ID
PPSLOT_APP_SECRET=YOUR_MERCHANT_APP_SECRET
PPSLOT_CALLBACK_IPS=
PPSLOT_LANGUAGE=my
PPSLOT_CURRENCY=MMK
PPSLOT_PRODUCT_ID=0
PPSLOT_PRODUCT_CODE=900001
PPSLOT_SLOT_GAME_TYPE_ID=1
PPSLOT_FISHING_GAME_TYPE_ID=8
PPSLOT_CARD_GAME_TYPE_ID=12
PPSLOT_LOTTERY_GAME_TYPE_ID=5
```

Never commit a real `PPSLOT_APP_SECRET`. Keep it only in the production `.env`/secret store. Configure `PPSLOT_CALLBACK_IPS` once the callback source IPs are known. If the site is behind a reverse proxy, Laravel trusted-proxy configuration must make `Request::ip()` resolve the real provider source IP before enabling the allowlist.

## updatesky merchant configuration

Create or update the merchant/operator in updatesky with the same AppID/AppSecret used by Laravel.

Required state:

```text
Merchant/operator enabled
WalletMode = Seamless
ReviewStatus = approved (when review workflow is enabled)
CurrencyKey = merchant settlement currency
Address = https://ppslot.site/api/provider/ppslot
WhiteIps = ppslot.site application server public IP(s)
```

Do not infer the numeric enabled/disabled value manually; use the current updatesky admin UI/runtime state and verify it with the authenticated API health check.

updatesky calls these Laravel endpoints:

```text
POST https://ppslot.site/api/provider/ppslot/Cash/Get
POST https://ppslot.site/api/provider/ppslot/Cash/TransferInOut
```

`Cash/Get` returns the local `users.balance`. `Cash/TransferInOut` is idempotent by `(provider, TransactionID)`, uses a database transaction and `lockForUpdate()`, prevents negative balances, validates `bet/win/refund` amount direction, strips `AppSecret` before storing raw request metadata, and returns the previously committed balance on a duplicate provider retry.

## Remote PPSLOT API flow

Laravel authenticates to `https://game.ppslot.site` with HTTP headers:

```text
AppID: PPSLOT_APP_ID
AppSecret: PPSLOT_APP_SECRET
Content-Type: application/json
```

Game catalog:

```text
POST /api/v1/game/list
```

Launch:

```text
POST /api/v1/game/launch
```

Current updatesky launch payload used by `PpSlotProvider`:

```json
{
  "UserID": "player_username",
  "GameID": "pg_1572362",
  "Language": "my",
  "Platform": "mobile"
}
```

The updatesky launch path ensures the remote player exists before returning a launch URL, so Laravel does not need a separate player-create call in the normal launch flow.

## Local application launch flow

Protected application endpoint:

```text
POST /api/games/launch
```

Legacy frontend alias retained for application compatibility:

```text
POST /api/seamless/launch-game
```

Recommended request:

```json
{
  "game_id": 123,
  "language": "my",
  "platform": "mobile"
}
```

`LaunchGameController` resolves the active local `GameList`, selects the driver through `ProviderManager`, then delegates the provider-specific request to `PpSlotProvider`.

Normalized success response:

```json
{
  "code": 200,
  "message": "Game launched successfully.",
  "url": "https://...",
  "content": null
}
```

## Catalog synchronization

PPSLOT is authoritative for the external game catalog. Do not seed legacy external provider products/games into new databases.

Sync:

```bash
php artisan provider:sync-games ppslot --language=my
```

Only after verifying that PPSLOT returned a complete catalog, missing local PPSLOT games may be disabled with:

```bash
php artisan provider:sync-games ppslot --language=my --deactivate-missing
```

The sync maps the remote catalog into `products`, `game_lists`, and `game_type_product` and marks records with `provider=ppslot`.

## Existing database migration away from the old provider

First inspect what would be disabled:

```bash
php artisan provider:disable-legacy
```

This is a dry run. After reviewing the printed product list:

```bash
php artisan provider:disable-legacy --apply
```

The command disables non-PPSLOT provider products and games without deleting historical rows. It does not remove transaction/report history.

Legacy GSCPlus seamless callbacks and the old generic provider transaction callback are no longer registered in `routes/api.php`. PPSLOT callbacks are the only external provider wallet endpoints exposed by the application.

## Health check

After configuring credentials and running migrations:

```bash
php artisan provider:ppslot-health --language=my
```

The command verifies:

- PPSLOT base URL, AppID and AppSecret are configured.
- `provider_wallet_transactions` exists.
- local PPSLOT product/game counts.
- remote `/api/v1/game/list` authentication and response parsing.
- callback URLs derived from `APP_URL`.

A successful remote catalog health check confirms AppID/AppSecret/API connectivity. A real game launch and a controlled seamless-wallet bet/win round are still required before production cutover.

## Production deployment order

```bash
php artisan down
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan provider:ppslot-health --language=my
php artisan provider:sync-games ppslot --language=my
php artisan provider:disable-legacy
# Review the dry-run output, then:
php artisan provider:disable-legacy --apply
php artisan config:cache
php artisan route:cache
php artisan up
```

Then verify:

```text
1. Player login on ppslot.site
2. /api/game_types and provider/game list show PPSLOT-backed records only
3. /api/games/launch returns a fresh PPSLOT launch URL
4. Cash/Get returns the same balance shown in ppslot.site
5. A bet produces one negative idempotent wallet transaction
6. A win/refund produces the expected positive transaction
7. Retrying the same TransactionID does not change balance twice
8. Old external-provider callback URLs return 404
```

## Source ownership boundaries

Keep provider-specific auth, payloads and response parsing inside `app/Services/GameProviders/Adapters`. Keep lobby controllers provider-agnostic and filter them using provider keys registered in `config/game_providers.php`. Keep PPSLOT wallet callbacks in `PpSlotWalletController`. Do not add old provider request formats or credentials back into `LaunchGameController`.
