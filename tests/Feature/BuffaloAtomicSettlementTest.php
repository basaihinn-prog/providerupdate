<?php

namespace Tests\Feature;

use App\Models\GameRoundSettlement;
use App\Models\User;
use App\Services\CustomWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BuffaloAtomicSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function player(float $balance): User
    {
        return User::create([
            'user_name' => 'buffalo-test-player',
            'name' => 'Buffalo Test Player',
            'password' => bcrypt('not-used'),
            'type' => 'player',
            'balance' => $balance,
            'status' => 1,
        ]);
    }

    public function test_same_round_retry_does_not_charge_wallet_twice(): void
    {
        $user = $this->player(100.00);
        $wallet = app(CustomWalletService::class);
        $meta = ['request_fingerprint' => hash('sha256', 'same-spin')];

        $first = $wallet->settleGameRound($user, 'buffalo', 'request-000000000001', 'BUFFALO1024', 10, 4, $meta);
        $retry = $wallet->settleGameRound($user, 'buffalo', 'request-000000000001', 'BUFFALO1024', 10, 4, $meta);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($retry['duplicate']);
        $this->assertSame('94.00', (string) $user->fresh()->balance);
        $this->assertSame(1, GameRoundSettlement::query()->where('provider', 'buffalo')->count());
    }

    public function test_reusing_round_id_with_different_payload_is_rejected(): void
    {
        $user = $this->player(100.00);
        $wallet = app(CustomWalletService::class);
        $meta = ['request_fingerprint' => hash('sha256', 'same-spin')];

        $wallet->settleGameRound($user, 'buffalo', 'request-000000000002', 'BUFFALO1024', 10, 4, $meta);

        $this->expectException(RuntimeException::class);
        $wallet->settleGameRound($user, 'buffalo', 'request-000000000002', 'BUFFALO1024', 10, 5, $meta);
    }

    public function test_zero_bet_zero_win_free_spin_is_still_recorded_idempotently(): void
    {
        $user = $this->player(5.00);
        $wallet = app(CustomWalletService::class);
        $meta = ['request_fingerprint' => hash('sha256', 'free-spin-loss')];

        $first = $wallet->settleGameRound($user, 'buffalo', 'request-000000000004', 'BUFFALO1024', 0, 0, $meta);
        $retry = $wallet->settleGameRound($user, 'buffalo', 'request-000000000004', 'BUFFALO1024', 0, 0, $meta);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($retry['duplicate']);
        $this->assertSame('5.00', (string) $user->fresh()->balance);
        $this->assertSame(1, GameRoundSettlement::query()->where('round_id', 'request-000000000004')->count());
    }

    public function test_insufficient_balance_rolls_back_settlement_and_wallet_mutation(): void
    {
        $user = $this->player(5.00);
        $wallet = app(CustomWalletService::class);

        $this->expectException(RuntimeException::class);
        try {
            $wallet->settleGameRound($user, 'buffalo', 'request-000000000003', 'BUFFALO1024', 10, 0, [
                'request_fingerprint' => hash('sha256', 'insufficient'),
            ]);
        } finally {
            $this->assertSame('5.00', (string) $user->fresh()->balance);
            $this->assertSame(0, GameRoundSettlement::query()->count());
        }
    }
}
