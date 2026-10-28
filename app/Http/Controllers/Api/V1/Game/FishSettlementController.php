<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\CustomTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FishSettlementController extends Controller
{
    /**
     * Trusted server-to-server endpoint. Signature covers the exact raw request body.
     * A successful call atomically debits the stake, credits gross payout, and records
     * one immutable round row. Replaying the same game_key + round_id cannot pay twice.
     */
    public function settle(Request $request)
    {
        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Invalid service signature.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'player_id' => ['required', 'integer', 'min:1'],
            'game_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'round_id' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'bet_amount' => ['required', 'numeric', 'gt:0', 'max:1000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payout_amount' => ['required', 'numeric', 'min:0', 'max:1000000000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid settlement payload.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $gameKey = $data['game_key'];
        $configuredGame = (string) config('fish_session.game', '');
        if ($configuredGame === '') {
            return response()->json(['message' => 'Fish game settlement is not configured.'], 503);
        }
        if (! hash_equals($configuredGame, $gameKey)) {
            return response()->json(['message' => 'Game key is not registered for this settlement service.'], 403);
        }

        $roundId = $data['round_id'];
        $bet = $this->toMinorUnits((string) $data['bet_amount']);
        $payout = $this->toMinorUnits((string) $data['payout_amount']);

        try {
            $result = DB::transaction(function () use ($data, $gameKey, $roundId, $bet, $payout) {
                // Lock the player first so concurrent rounds for one player serialize.
                $player = User::query()->whereKey($data['player_id'])->lockForUpdate()->first();
                if (! $player || (int) $player->type !== UserType::Player->value) {
                    return ['status' => 404, 'body' => ['message' => 'Player not found.']];
                }

                $existing = DB::table('fish_game_rounds')
                    ->where('game_key', $gameKey)->where('round_id', $roundId)->first();

                if ($existing) {
                    $same = (int) $existing->player_id === (int) $player->id
                        && $this->toMinorUnits((string) $existing->bet_amount) === $bet
                        && $this->toMinorUnits((string) $existing->payout_amount) === $payout;
                    if (! $same) {
                        return ['status' => 409, 'body' => ['message' => 'Round ID already used with different settlement data.']];
                    }

                    return ['status' => 200, 'body' => [
                        'message' => 'Settlement already applied.',
                        'idempotent_replay' => true,
                        'round_id' => $roundId,
                        'balance' => number_format((float) $existing->balance_after, 2, '.', ''),
                    ]];
                }

                $before = $this->toMinorUnits((string) $player->getRawOriginal('balance'));
                if ($before < $bet) {
                    return ['status' => 409, 'body' => ['message' => 'Insufficient wallet balance.']];
                }

                $after = $before - $bet + $payout;
                $player->balance = $this->fromMinorUnits($after);
                $player->save();

                // Existing providerupdate wallet ledger remains the financial audit trail.
                CustomTransaction::create([
                    'user_id' => $player->id,
                    'target_user_id' => $player->id,
                    'amount' => $this->fromMinorUnits($bet),
                    'type' => 'withdraw',
                    'transaction_name' => 'fish_bet',
                    'old_balance' => $this->fromMinorUnits($before),
                    'new_balance' => $this->fromMinorUnits($before - $bet),
                    'meta' => ['game_key' => $gameKey, 'round_id' => $roundId, 'leg' => 'bet'],
                    'uuid' => (string) Str::uuid(),
                    'confirmed' => true,
                ]);

                if ($payout > 0) {
                    CustomTransaction::create([
                        'user_id' => $player->id,
                        'target_user_id' => $player->id,
                        'amount' => $this->fromMinorUnits($payout),
                        'type' => 'deposit',
                        'transaction_name' => 'fish_payout',
                        'old_balance' => $this->fromMinorUnits($before - $bet),
                        'new_balance' => $this->fromMinorUnits($after),
                        'meta' => ['game_key' => $gameKey, 'round_id' => $roundId, 'leg' => 'payout'],
                        'uuid' => (string) Str::uuid(),
                        'confirmed' => true,
                    ]);
                }

                DB::table('fish_game_rounds')->insert([
                    'player_id' => $player->id,
                    'game_key' => $gameKey,
                    'round_id' => $roundId,
                    'currency' => strtoupper($data['currency'] ?? 'MMK'),
                    'bet_amount' => $this->fromMinorUnits($bet),
                    'payout_amount' => $this->fromMinorUnits($payout),
                    'balance_before' => $this->fromMinorUnits($before),
                    'balance_after' => $this->fromMinorUnits($after),
                    'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
                    'settled_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return ['status' => 200, 'body' => [
                    'message' => 'Settlement applied.',
                    'idempotent_replay' => false,
                    'round_id' => $roundId,
                    'balance' => $this->fromMinorUnits($after),
                ]];
            }, 3);

            return response()->json($result['body'], $result['status'])->header('Cache-Control', 'no-store');
        } catch (QueryException $e) {
            // A unique-key race may occur if two requests use the same round ID.
            $existing = DB::table('fish_game_rounds')->where('game_key', $gameKey)->where('round_id', $roundId)->first();
            if ($existing) {
                return response()->json(['message' => 'Round already settled; query status before retrying.'], 409);
            }
            report($e);
            return response()->json(['message' => 'Settlement could not be recorded.'], 500);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Settlement could not be recorded.'], 500);
        }
    }

    /**
     * Admin-facing report. Actual RTP = gross payouts / stakes * 100.
     * The target is an audited monitoring/configuration value, not an outcome override.
     */
    public function report(Request $request)
    {
        if (! $this->isRtpAdmin($request->user())) {
            return response()->json(['message' => 'Owner access required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'game_key' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid report filters.', 'errors' => $validator->errors()], 422);
        }

        $query = DB::table('fish_game_rounds');
        if ($request->filled('game_key')) {
            $query->where('game_key', $request->string('game_key')->toString());
        }
        if ($request->filled('from')) {
            $query->where('settled_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('settled_at', '<=', $request->date('to')->endOfDay());
        }

        $totals = (clone $query)->selectRaw('COUNT(*) as rounds, COALESCE(SUM(bet_amount), 0) as total_bets, COALESCE(SUM(payout_amount), 0) as total_payouts')->first();
        $bets = (float) $totals->total_bets;
        $payouts = (float) $totals->total_payouts;

        $configs = DB::table('fish_rtp_configs')->when($request->filled('game_key'), function ($q) use ($request) {
            $q->where('game_key', $request->string('game_key')->toString());
        })->orderBy('game_key')->get();

        return response()->json([
            'rounds' => (int) $totals->rounds,
            'total_bets' => number_format($bets, 2, '.', ''),
            'total_gross_payouts' => number_format($payouts, 2, '.', ''),
            'actual_rtp_percent' => $bets > 0 ? round(($payouts / $bets) * 100, 4) : null,
            'target_configs' => $configs->map(fn ($config) => [
                'game_key' => $config->game_key,
                'target_rtp_percent' => round(((int) $config->target_rtp_bps) / 100, 2),
                'enabled' => (bool) $config->enabled,
                'version' => (int) $config->version,
                'change_reason' => $config->change_reason,
                'updated_at' => $config->updated_at,
            ])->values(),
            'note' => 'RTP is measured from recorded rounds. Target configuration does not alter game outcomes.',
        ])->header('Cache-Control', 'no-store');
    }

    public function updateTarget(Request $request, string $gameKey)
    {
        if (! $this->isRtpAdmin($request->user())) {
            return response()->json(['message' => 'Owner access required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'target_rtp_percent' => ['required', 'numeric', 'min:1', 'max:200'],
            'enabled' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid RTP configuration.', 'errors' => $validator->errors()], 422);
        }

        $targetBps = (int) round(((float) $request->input('target_rtp_percent')) * 100);
        $enabled = $request->boolean('enabled', true);
        $actor = $request->user();

        $config = DB::transaction(function () use ($gameKey, $targetBps, $enabled, $request, $actor) {
            $current = DB::table('fish_rtp_configs')->where('game_key', $gameKey)->lockForUpdate()->first();
            $fromBps = $current ? (int) $current->target_rtp_bps : null;
            $fromEnabled = $current ? (bool) $current->enabled : null;
            $version = $current ? ((int) $current->version + 1) : 1;

            if ($current) {
                DB::table('fish_rtp_configs')->where('game_key', $gameKey)->update([
                    'target_rtp_bps' => $targetBps,
                    'enabled' => $enabled,
                    'version' => $version,
                    'updated_by' => $actor->id,
                    'change_reason' => $request->input('reason'),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('fish_rtp_configs')->insert([
                    'game_key' => $gameKey,
                    'target_rtp_bps' => $targetBps,
                    'enabled' => $enabled,
                    'version' => $version,
                    'updated_by' => $actor->id,
                    'change_reason' => $request->input('reason'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('fish_rtp_config_audits')->insert([
                'game_key' => $gameKey,
                'from_rtp_bps' => $fromBps,
                'to_rtp_bps' => $targetBps,
                'from_enabled' => $fromEnabled,
                'to_enabled' => $enabled,
                'actor_id' => $actor->id,
                'reason' => $request->input('reason'),
                'created_at' => now(),
            ]);

            return DB::table('fish_rtp_configs')->where('game_key', $gameKey)->first();
        }, 3);

        return response()->json([
            'game_key' => $config->game_key,
            'target_rtp_percent' => round(((int) $config->target_rtp_bps) / 100, 2),
            'enabled' => (bool) $config->enabled,
            'version' => (int) $config->version,
            'note' => 'This is an audited target for monitoring; it does not alter individual game outcomes.',
        ])->header('Cache-Control', 'no-store');
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('fish_settlement.secret', '');
        $timestamp = (string) $request->header('X-Fish-Timestamp', '');
        $signature = strtolower((string) $request->header('X-Fish-Signature', ''));
        $ttl = max(30, min((int) config('fish_settlement.signature_ttl_seconds', 300), 600));

        if (strlen($secret) < 32 || ! ctype_digit($timestamp) || strlen($signature) !== 64) {
            return false;
        }
        if (abs(now()->timestamp - (int) $timestamp) > $ttl) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        return hash_equals($expected, $signature);
    }

    private function isRtpAdmin(?User $user): bool
    {
        return $user !== null && (int) $user->type === UserType::Owner->value;
    }

    private function toMinorUnits(string $amount): int
    {
        $parts = explode('.', $amount, 2);
        $whole = (int) $parts[0];
        $fraction = str_pad($parts[1] ?? '', 2, '0');
        return ($whole * 100) + (int) substr($fraction, 0, 2);
    }

    private function fromMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
