<?php

namespace App\Services;

use App\Models\GameInstance;
use App\Models\SlotGameSession;
use App\Models\SpinHistory;
use App\Models\User;
use App\Services\Slot\BonusEngine;
use App\Services\Slot\ProvablyFairService;
use App\Services\Slot\ReelStripGenerator;
use App\Services\Slot\Ways1024Calculator;
use Illuminate\Support\Facades\DB;

class SlotGameService
{
    public function __construct(
        protected GameCreatorService $gameCreatorService,
        protected GameSessionService $gameSessionService,
        protected CustomWalletService $walletService,
        protected GgrTrackerService $ggrTrackerService,
        protected ReelStripGenerator $reelStripGenerator,
        protected Ways1024Calculator $waysCalculator,
        protected BonusEngine $bonusEngine,
        protected ProvablyFairService $provablyFairService,
    ) {
    }

    public function legacyStartInfo(User $user, ?string $requestedGameCode = null, array $meta = []): array
    {
        $game = $this->gameCreatorService->resolveRequestedGame($requestedGameCode);
        $session = $this->gameSessionService->initSession(
            $game,
            $user->user_name,
            $user,
            $meta['client_seed'] ?? null,
            $meta
        );

        return [
            'status' => 'ok',
            'sessionToken' => $session->session_token,
            'gameCode' => $game->game_code,
            'balance' => $this->walletService->getBalance($user),
            'bet' => 0,
            'freeSpinCount' => $session->free_spins_remaining,
            'freeSpinTotal' => $session->free_spins_awarded,
            'freeSpinStatus' => $session->free_spins_remaining > 0 ? 'active' : '',
            'freeSpinWinCount' => 0,
            'freeSpinMultiply' => (float) $session->free_spin_multiplier,
        ];
    }

    public function legacySpin(User $user, float $betAmount, ?string $requestedGameCode = null, array $meta = []): array
    {
        $requestId = trim((string) ($meta['request_id'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $requestId)) {
            throw new \RuntimeException('A unique request_id (16-128 letters, digits, _ or -) is required for every spin.');
        }

        $game = $this->gameCreatorService->resolveRequestedGame($requestedGameCode);
        $fingerprint = hash('sha256', json_encode([
            'user_id' => (int) $user->getKey(),
            'game_code' => $game->game_code,
            'bet_amount' => number_format(round($betAmount, 2), 2, '.', ''),
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR));
        $meta['request_id'] = $requestId;
        $meta['request_fingerprint'] = $fingerprint;

        return DB::transaction(function () use ($user, $game, $betAmount, $meta, $requestId, $fingerprint) {
            $existing = \App\Models\GameRoundSettlement::query()
                ->where('provider', 'buffalo')
                ->where('round_id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ((int) $existing->user_id !== (int) $user->getKey()
                    || $existing->game_code !== $game->game_code
                    || ! hash_equals((string) data_get($existing->meta, 'request_fingerprint', ''), $fingerprint)) {
                    throw new \RuntimeException('request_id was already used for a different spin payload.');
                }

                $replay = data_get($existing->response, 'engine_result');
                if (! is_array($replay)) {
                    throw new \RuntimeException('This spin is recorded but its response is incomplete; reconciliation is required.');
                }

                return $replay;
            }

            $session = $this->gameSessionService->initSession(
                $game,
                $user->user_name,
                $user,
                $meta['client_seed'] ?? null,
                $meta
            );
            $result = $this->spinSession($game, $session, $betAmount, $user, $meta);
            if (isset($result['_replay_legacy']) && is_array($result['_replay_legacy'])) {
                return $result['_replay_legacy'];
            }

            $legacyResult = [
                'bet' => $result['bet_amount'],
                'balance' => $result['balance_after'],
                'winAmount' => $result['total_win_amount'],
                'freeSpinCount' => $result['feature_state']['free_spins_remaining'],
                'freeSpinTotal' => $result['feature_state']['free_spins_awarded_total'],
                'freeSpinStatus' => $result['feature_state']['free_spins_remaining'] > 0 ? 'active' : '',
                'freeSpinWinCount' => $result['feature_state']['is_free_spin'] ? 1 : 0,
                'freeSpinMultiply' => $result['feature_state']['free_spin_multiplier'],
                'matchNo' => $result['match_no'],
                'result' => [
                    'display' => $result['display_ids'],
                    'winPattern' => $result['win_patterns'],
                    'waysWins' => $result['wins'],
                    'proof' => $result['proof'],
                ],
                'auditTrail' => $result['audit_trail'],
                'ggr' => $result['ggr'],
            ];

            $settlement = \App\Models\GameRoundSettlement::query()
                ->where('provider', 'buffalo')
                ->where('round_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();
            $settlement->response = array_merge($settlement->response ?? [], ['engine_result' => $legacyResult]);
            $settlement->save();

            return $legacyResult;
        }, 3);
    }

    public function initProviderSession(
        GameInstance $game,
        string $playerIdentifier,
        ?User $user = null,
        ?string $clientSeed = null,
        array $meta = []
    ): array {
        $session = $this->gameSessionService->initSession($game, $playerIdentifier, $user, $clientSeed, $meta);
        $runtimeConfig = $this->gameCreatorService->buildRuntimeConfig($game);

        return [
            'game_code' => $game->game_code,
            'display_name' => $game->display_name,
            'session_token' => $session->session_token,
            'player_identifier' => $session->player_identifier,
            'client_seed' => $session->client_seed,
            'balance' => $user ? $this->walletService->getBalance($user) : null,
            'grid' => $runtimeConfig['grid'],
            'rtp_target' => $runtimeConfig['rtp_target'],
            'volatility' => $runtimeConfig['volatility'],
            'feature_state' => [
                'free_spins_remaining' => $session->free_spins_remaining,
                'free_spins_awarded_total' => $session->free_spins_awarded,
                'free_spin_multiplier' => (float) $session->free_spin_multiplier,
            ],
        ];
    }

    public function spinSession(
        GameInstance $game,
        SlotGameSession $session,
        float $betAmount,
        ?User $user = null,
        array $meta = []
    ): array {
        $runtimeConfig = $this->gameCreatorService->buildRuntimeConfig($game);

        return DB::transaction(function () use ($game, $session, $betAmount, $user, $meta, $runtimeConfig) {
            $lockedSession = SlotGameSession::lockForUpdate()->findOrFail($session->id);
            $lockedUser = $user ? User::lockForUpdate()->find($user->id) : null;
            $sessionMeta = $lockedSession->meta ?? [];
            $nonce = (int) ($sessionMeta['spin_nonce'] ?? 0) + 1;
            $isFreeSpin = $lockedSession->free_spins_remaining > 0;
            $chargedBet = $isFreeSpin ? 0.0 : round($betAmount, 2);
            $balanceBefore = $lockedUser ? round((float) $lockedUser->balance, 2) : 0.0;

            if (! $isFreeSpin && $lockedUser !== null && bccomp((string) $lockedUser->balance, (string) $chargedBet, 2) < 0) {
                throw new \RuntimeException('Insufficient balance');
            }

            if ($isFreeSpin) {
                $lockedSession->free_spins_remaining = max(0, $lockedSession->free_spins_remaining - 1);
            }

            $spinMath = $this->executeSpinMath(
                $runtimeConfig,
                [
                    'is_free_spin' => $isFreeSpin,
                    'free_spin_multiplier' => (float) $lockedSession->free_spin_multiplier,
                    'manual_coin_bonus_multiplier' => $sessionMeta['manual_coin_bonus_multiplier'] ?? null,
                ],
                $betAmount,
                $nonce,
                $lockedSession->client_seed,
                ! empty($meta['test_seed']) ? (string) $meta['test_seed'] : null
            );

            $lockedSession->free_spins_remaining += $spinMath['feature']['free_spins_awarded'];
            $lockedSession->free_spins_awarded += $spinMath['feature']['free_spins_awarded'];
            $lockedSession->free_spin_multiplier = $lockedSession->free_spins_remaining > 0
                ? max((float) $lockedSession->free_spin_multiplier, (float) $spinMath['feature']['free_spin_multiplier'])
                : 1;

            if ($spinMath['feature']['manual_bonus_consumed']) {
                unset($sessionMeta['manual_coin_bonus_multiplier']);
            }

            $totalWinAmount = $spinMath['total_win_amount'];
            $auditTrail = $spinMath['audit_trail'];
            $auditTrail['scatter_counts']['free_spin_triggers'] = (int) $spinMath['feature']['free_spins_awarded'];
            $auditTrail['bonus_credit_win'] = round((float) $spinMath['bonus_win_amount'], 2);
            $auditTrail['final_aggregated_credit_win'] = $totalWinAmount;

            if ($lockedUser !== null) {
                $requestId = trim((string) ($meta['request_id'] ?? ''));
                $requestFingerprint = trim((string) ($meta['request_fingerprint'] ?? ''));
                if ($requestId === '' || $requestFingerprint === '') {
                    throw new \RuntimeException('Idempotency request metadata is required for wallet-backed Buffalo spins.');
                }

                $existingSettlement = \App\Models\GameRoundSettlement::query()
                    ->where('provider', 'buffalo')
                    ->where('round_id', $requestId)
                    ->lockForUpdate()
                    ->first();

                if ($existingSettlement !== null) {
                    if ((int) $existingSettlement->user_id !== (int) $lockedUser->getKey()
                        || $existingSettlement->game_code !== $game->game_code
                        || ! hash_equals((string) data_get($existingSettlement->meta, 'request_fingerprint', ''), $requestFingerprint)) {
                        throw new \RuntimeException('request_id was already used for a different spin payload.');
                    }
                    $replay = data_get($existingSettlement->response, 'engine_result');
                    if (is_array($replay)) {
                        return ['_replay_legacy' => $replay];
                    }
                    throw new \RuntimeException('Existing spin settlement has no saved response; reconciliation is required.');
                }

                $this->walletService->settleGameRound(
                    $lockedUser,
                    'buffalo',
                    $requestId,
                    $game->game_code,
                    $chargedBet,
                    $totalWinAmount,
                    [
                        'request_fingerprint' => $requestFingerprint,
                        'match_no' => $spinMath['match_no'],
                        'spin_nonce' => $nonce,
                        'is_free_spin' => $isFreeSpin,
                    ]
                );
                $lockedUser->refresh();
            }

            $balanceAfter = $lockedUser ? round((float) $lockedUser->balance, 2) : round($balanceBefore - $chargedBet + $totalWinAmount, 2);
            $ggrDelta = round($chargedBet - $totalWinAmount, 2);
            $lockedSession->total_bet = round((float) $lockedSession->total_bet + $chargedBet, 2);
            $lockedSession->total_win = round((float) $lockedSession->total_win + $totalWinAmount, 2);
            $lockedSession->ggr = round((float) $lockedSession->ggr + $ggrDelta, 2);
            $lockedSession->last_activity_at = now();
            $sessionMeta['spin_nonce'] = $nonce;
            $lockedSession->meta = $sessionMeta;
            $lockedSession->save();

            $history = SpinHistory::create([
                'game_instance_id' => $game->id,
                'slot_game_session_id' => $lockedSession->id,
                'user_id' => $lockedUser?->id,
                'match_no' => $spinMath['match_no'],
                'bet_amount' => $chargedBet,
                'base_win_amount' => $spinMath['base_win_amount'],
                'scatter_win_amount' => $spinMath['scatter_win_amount'],
                'bonus_win_amount' => $spinMath['bonus_win_amount'],
                'total_win_amount' => $totalWinAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'ggr_delta' => $ggrDelta,
                'is_free_spin' => $isFreeSpin,
                'server_seed_hash' => $spinMath['proof']['server_seed_hash'],
                'server_seed' => $spinMath['proof']['server_seed'],
                'client_seed' => $spinMath['proof']['client_seed'],
                'nonce' => $spinMath['proof']['nonce'],
                'grid' => [
                    'rows' => $spinMath['display_symbols'],
                    'stops' => $spinMath['stops'],
                ],
                'wins' => $spinMath['wins'],
                'feature_state' => [
                    'is_free_spin' => $isFreeSpin,
                    'free_spins_awarded_this_spin' => $spinMath['feature']['free_spins_awarded'],
                    'free_spins_remaining' => $lockedSession->free_spins_remaining,
                    'free_spins_awarded_total' => $lockedSession->free_spins_awarded,
                    'free_spin_multiplier' => (float) $lockedSession->free_spin_multiplier,
                ],
                'meta' => $meta,
            ]);

            $metric = $this->ggrTrackerService->recordSpin($game, $chargedBet, $totalWinAmount, $totalWinAmount > 0);

            return [
                'spin_id' => $history->id,
                'match_no' => $spinMath['match_no'],
                'bet_amount' => $chargedBet,
                'display_symbols' => $spinMath['display_symbols'],
                'display_ids' => $spinMath['display_ids'],
                'stops' => $spinMath['stops'],
                'wins' => $spinMath['wins'],
                'win_patterns' => $spinMath['win_patterns'],
                'base_win_amount' => $spinMath['base_win_amount'],
                'scatter_win_amount' => $spinMath['scatter_win_amount'],
                'bonus_win_amount' => $spinMath['bonus_win_amount'],
                'total_win_amount' => $totalWinAmount,
                'proof' => $spinMath['proof'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'feature_state' => [
                    'is_free_spin' => $isFreeSpin,
                    'free_spins_awarded_this_spin' => $spinMath['feature']['free_spins_awarded'],
                    'free_spins_remaining' => $lockedSession->free_spins_remaining,
                    'free_spins_awarded_total' => $lockedSession->free_spins_awarded,
                    'free_spin_multiplier' => (float) $lockedSession->free_spin_multiplier,
                    'bonus_events' => $spinMath['bonus_events'],
                ],
                'audit_trail' => $auditTrail,
                'ggr' => [
                    'spin_delta' => $ggrDelta,
                    'session_ggr' => (float) $lockedSession->ggr,
                    'game_total_bet' => (float) $metric->total_bet,
                    'game_total_win' => (float) $metric->total_win,
                    'game_ggr' => (float) $metric->ggr,
                    'observed_rtp' => (float) $metric->observed_rtp,
                ],
            ];
        });
    }

    private function executeSpinMath(
        array $runtimeConfig,
        array $sessionState,
        float $betAmount,
        int $nonce,
        ?string $clientSeed = null,
        ?string $testSeed = null
    ): array {
        $rng = $this->provablyFairService->createRng($clientSeed, $nonce, $testSeed);
        $grid = $this->reelStripGenerator->generate(
            $runtimeConfig['active_reels'] ?? [],
            $rng,
            (int) ($runtimeConfig['grid']['rows'] ?? 4),
            $runtimeConfig['wild_placement'] ?? []
        );
        $featureMultiplier = (bool) ($sessionState['is_free_spin'] ?? false)
            ? (float) ($sessionState['free_spin_multiplier'] ?? 1.0)
            : 1.0;
        $waysResult = $this->waysCalculator->calculate($grid['reels'], $betAmount, $runtimeConfig, $featureMultiplier);
        $bonusResult = $this->bonusEngine->evaluate($grid['reels'], $betAmount, $runtimeConfig, $rng, $sessionState);
        $symbolMap = $this->buildSymbolIdMap($runtimeConfig['symbols'] ?? []);
        $displayIds = $this->mapRowsToIds($grid['rows'], $symbolMap);
        $totalWinAmount = round(
            $waysResult['base_win_amount'] + $waysResult['scatter_win_amount'] + $bonusResult['bonus_win_amount'],
            2
        );

        return [
            'match_no' => $this->generateMatchNo(),
            'display_symbols' => $grid['rows'],
            'display_ids' => $displayIds,
            'stops' => $grid['stops'],
            'wins' => array_merge($waysResult['wins'], $bonusResult['bonus_events']),
            'win_patterns' => $waysResult['win_patterns'],
            'base_win_amount' => $waysResult['base_win_amount'],
            'scatter_win_amount' => $waysResult['scatter_win_amount'],
            'bonus_win_amount' => $bonusResult['bonus_win_amount'],
            'bonus_events' => $bonusResult['bonus_events'],
            'audit_trail' => $waysResult['audit_trail'],
            'total_win_amount' => $totalWinAmount,
            'feature' => [
                'free_spins_awarded' => $bonusResult['free_spins_awarded'],
                'free_spin_multiplier' => $bonusResult['free_spin_multiplier'],
                'manual_bonus_consumed' => $bonusResult['manual_bonus_consumed'],
            ],
            'proof' => $this->provablyFairService->exportProof($rng),
        ];
    }

    private function buildSymbolIdMap(array $symbols): array
    {
        $map = [];

        foreach ($symbols as $symbolName => $symbolConfig) {
            $map[$symbolName] = (int) ($symbolConfig['id'] ?? 0);
        }

        return $map;
    }

    private function mapRowsToIds(array $rowDisplay, array $symbolMap): array
    {
        $display = [];

        foreach ($rowDisplay as $rowIndex => $symbols) {
            $display[$rowIndex] = [];

            foreach ($symbols as $reelIndex => $symbolName) {
                $display[$rowIndex][$reelIndex] = $symbolMap[$symbolName] ?? -1;
            }
        }

        return $display;
    }

    private function generateMatchNo(): string
    {
        return strtoupper(bin2hex(random_bytes(8)));
    }
}
