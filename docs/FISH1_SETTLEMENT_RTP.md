# Fish1 settlement ledger and RTP monitoring

## Scope and safety boundary

This adds the providerupdate-side settlement receiver and database records. It does not implement the Cocos fish simulation or decide which fish are hit. The trusted server-side game service must determine outcomes and send one signed settlement per completed round. Never accept stake/payout values from the browser, and never use a target RTP to force individual outcomes or change odds for a specific player.

The RTP target is a configuration/monitoring reference. Actual RTP is calculated from recorded rounds as total gross payouts / total stakes * 100. Confirm with the game's math specification whether payout amounts include returned stake before comparing this metric with certified/provider RTP.

## Database changes

Migrations create:

- `fish_game_rounds`: unique `game_key + round_id`, player, stake, gross payout, before/after balance, currency, settlement time and optional metadata.
- `fish_rtp_configs`: target RTP basis points (9600 = 96.00%), enabled flag, version, last actor and reason.
- `fish_rtp_config_audits`: append-only record of each target/status change and reason.

The settlement also writes the existing `custom_transactions` wallet ledger and updates `users.balance` in one DB transaction. It rejects insufficient balance and replayed round IDs; same-player duplicate requests with identical amounts return the recorded balance without crediting again.

## Required environment

Set a random secret of at least 32 bytes in the protected providerupdate environment and the trusted fish backend only:

```dotenv
FISH_SETTLEMENT_SECRET=<same-random-secret-on-both-servers>
FISH_SETTLEMENT_SIGNATURE_TTL_SECONDS=300
```

Do not reuse the launch-ticket secret unless there is a deliberate secret-management decision. Do not expose either secret to Cocos/browser code.

## Signed settlement protocol

Endpoint: `POST /api/fish/settle`

Headers:
- `X-Fish-Timestamp`: current Unix timestamp in seconds
- `X-Fish-Signature`: lowercase hex HMAC-SHA256 of `timestamp + "." + exact_raw_HTTP_body`

JSON example (only from the trusted backend):
```json
{
  "player_id": 123,
  "game_key": "fish1",
  "round_id": "round-unique-id",
  "currency": "MMK",
  "bet_amount": "100.00",
  "payout_amount": "0.00",
  "metadata": {"session_id": "opaque-session-id"}
}
```

Signature generation must use the exact bytes sent as the body. The request timestamp must be within the configured window. Use TLS between servers and restrict network access where practical. A successful response is not a substitute for reconciling wallet and round records.

## RTP admin page

The owner-only web page is available at `/admin/fish-rtp` after migrations run. It shows per-game recorded stakes, gross payouts, measured RTP, target/version, and the latest configuration audit events. Its form writes the same configuration and audit tables as the API. It is not a game-math editor and cannot change individual outcomes.

## RTP owner APIs

Both routes require a Sanctum-authenticated user whose `users.type` is Owner (10):

- `GET /api/fish/admin/rtp?game_key=fish1&from=2026-10-01&to=2026-10-28`
- `PUT /api/fish/admin/rtp/fish1`

PUT body:
```json
{
  "target_rtp_percent": 96,
  "enabled": true,
  "reason": "Approved configuration review ticket #1234"
}
```

The reason is mandatory and must be at least 10 characters. Target values are bounded to 1-200%. Each change increments a version and writes an audit row. This endpoint records a target; it does not alter outcomes.

## Deployment sequence

1. Back up the providerupdate database and test on staging first.
2. Deploy code to staging; set `FISH_SETTLEMENT_SECRET` in a protected environment file.
3. Run `php artisan migrate --force` only after reviewing the migration diff and confirming the correct database connection.
4. Run `php artisan route:list --path=api/fish`, `php artisan test`, and a signed staging handshake using a dedicated test player.
5. Verify one round creates one `fish_game_rounds` row, correct custom transaction entries and correct `users.balance`; resend the exact same round and verify the balance does not change.
6. Test insufficient balance, bad signature, expired timestamp, duplicate round ID with altered values, RTP report filters, non-owner denial, and audit history.
7. Reconcile sample-round RTP against the game math definition before production. Keep real-money traffic disabled until the trusted fish engine and idempotent round contract are integrated and verified.

Never run migrations against production before backup and staging verification.
