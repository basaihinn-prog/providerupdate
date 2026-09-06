# Production Game Provider Integration

The application now uses a provider-adapter architecture. Core controllers do not contain provider-specific request signing or payload formats. Each provider is implemented behind `GameProviderInterface` and registered in `config/game_providers.php`.

## Current production driver: PPSLOT / updatesky

Required environment variables:

```env
GAME_PROVIDER_DEFAULT=ppslot
PPSLOT_API_URL=https://game.ppslot.site
PPSLOT_APP_ID=YOUR_OPERATOR_APP_ID
PPSLOT_APP_SECRET=YOUR_OPERATOR_APP_SECRET
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

Never commit real AppSecret values. `PPSLOT_CALLBACK_IPS` is optional; when provider callback source IPs are known, set a comma-separated allowlist such as `203.0.113.10,203.0.113.11`.

## Deployment order

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan provider:sync-games ppslot --language=my
php artisan config:cache
```

After the catalog sync, verify that the generated/selected PPSLOT product is attached to the expected game types and that only configured provider adapters are exposed by the lobby APIs.

## Launch flow

Protected application endpoint:

```text
POST /api/games/launch
```

Legacy-compatible endpoint remains available:

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

Backward-compatible requests using `game_code`, `product_code`, and `game_type` are still accepted. The controller resolves the `GameList`, determines the provider from the related `Product`, and delegates launch to `ProviderManager`.

Normalized success response:

```json
{
  "code": 200,
  "message": "Game launched successfully.",
  "url": "https://...",
  "content": null
}
```

## PPSLOT game-server operator configuration

On updatesky/PPSLOT create or update the operator using the same AppID/AppSecret configured in Laravel.

Set:

```text
WalletMode = Seamless
Status = enabled
ReviewStatus = approved
CurrencyKey = the operator currency
Address = https://YOUR-LARAVEL-DOMAIN/api/provider/ppslot
```

Also allow the Laravel server public IP in the updatesky operator IP whitelist.

PPSLOT will then call:

```text
POST https://YOUR-LARAVEL-DOMAIN/api/provider/ppslot/Cash/Get
POST https://YOUR-LARAVEL-DOMAIN/api/provider/ppslot/Cash/TransferInOut
```

`Cash/TransferInOut` is idempotent by `(provider, TransactionID)`, runs under a database transaction, locks the user wallet row, prevents negative balances, validates `bet/win/refund` amount direction, removes `AppSecret` before storing request metadata, and returns the previously committed balance when the provider retries the same transaction.

## Adding another provider

1. Create an adapter under `app/Services/GameProviders/Adapters` implementing `GameProviderInterface`.
2. Add the driver to `config/game_providers.php`.
3. Store the same lowercase driver key in `products.provider` for that provider.
4. Add a provider-specific catalog normalizer/sync implementation if its catalog format differs.
5. Add provider-specific webhook routes/controllers only when its wallet protocol requires them.

Do not add provider request formats, signatures, credentials, or endpoint-specific fields back into `LaunchGameController`. Provider-specific translation belongs inside the adapter.

## Legacy provider code

Legacy GSCPlus callback routes remain in place for rollback compatibility, but lobby queries expose only providers registered in `config/game_providers.php`. This lets old database records remain recoverable without being selectable in the production game lobby.
