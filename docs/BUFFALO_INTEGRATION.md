# Buffalo integration in providerupdate

## Architecture

- Buffalo's server-side `SlotGameService` generates the authoritative reel outcome, payout, proof, and feature/session state.
- providerupdate owns the player balance. The ported slot engine must not call separate stake-withdrawal and win-deposit operations; it settles a spin through `CustomWalletService::settleGameRound()`.
- `game_round_settlements` stores one settlement per `(provider, round_id)`. The Cocos client supplies a unique `request_id`; same-key retries replay the original response and do not run a second wallet mutation.
- The `BUFFALO_GAMEPLAY_ENABLED` flag defaults to `false`. Keep it false until staging verification is complete.

## Environment

Configure these on the providerupdate application; never commit secrets or a real player token.

```dotenv
BUFFALO_GAME_URL=https://games.example.com/buffalo/index.html
BUFFALO_API_URL=https://api.example.com/api/
BUFFALO_GAME_CODE=Buffalo
BUFFALO_GAMEPLAY_ENABLED=false
```

`BUFFALO_GAME_URL` is the HTTPS Cocos `index.html` URL. `BUFFALO_API_URL` is the public providerupdate API base and must include `/api/`. The Buffalo client reads `apiUrl` from the launch URL, so game assets and API can use different origins. providerupdate's existing CORS configuration permits API cross-origin requests; review/lock this down to the actual game origin before production.

The matching Buffalo Cocos change is tracked in [Buffalo PR #2](https://github.com/basaihinn-prog/Buffalo/pull/2). It must be merged/deployed with this integration before testing request idempotency from the browser.

## Staging sequence

1. Deploy this branch to an isolated staging app/database, not the production database.
2. Review the migrations, then inspect the planned schema changes:

   ```bash
   php artisan migrate:status
   php artisan migrate --pretend
   ```

3. Apply migrations to staging only:

   ```bash
   php artisan migrate --force
   ```

4. Clear cached Laravel configuration after updating environment variables:

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan route:list --path=start_info
   php artisan route:list --path=bet
   ```

5. Run the automated tests:

   ```bash
   php artisan test --filter=BuffaloAtomicSettlementTest
   php artisan test --filter=BuffaloGameLaunchUrlTest
   ```

6. Keep wagering disabled until tests pass. Then enable `BUFFALO_GAMEPLAY_ENABLED=true` in staging only and verify:
   - `POST /api/start_info` creates/returns a server-owned session and the current wallet balance.
   - `POST /api/bet` returns an engine-generated display, proof, match number, win and post-settlement balance.
   - Replaying the exact same request body with the same `request_id` returns the same match number/result and does not create another settlement or wallet transaction.
   - Reusing that `request_id` with a changed bet/domain/client seed is rejected.
   - An insufficient-balance spin leaves balance, session nonce, spin history, settlement rows and wallet audit rows unchanged.
   - Concurrent identical requests produce one committed spin/settlement and identical replay responses.
   - CORS works from the actual HTTPS game origin and no passcode appears in logs.

## Current verification status

The code changes have been committed to a draft PR, but PHP lint, PHPUnit, migrations, concurrent requests, browser checks and VPS deployment have not been run from this session. Do not merge or enable production wagering until the staging sequence above passes.
