<?php

namespace Tests\Feature;

use Tests\TestCase;

class FishSettlementSignatureTest extends TestCase
{
    public function test_settlement_rejects_missing_signature(): void
    {
        config(['fish_settlement.secret' => str_repeat('s', 40)]);

        $this->postJson('/api/fish/settle', [
            'player_id' => 1,
            'game_key' => 'fish1',
            'round_id' => 'test-round',
            'bet_amount' => '1.00',
            'payout_amount' => '0.00',
        ])->assertUnauthorized();
    }

    public function test_settlement_rejects_missing_server_secret(): void
    {
        config(['fish_settlement.secret' => '']);

        $this->postJson('/api/fish/settle', [
            'player_id' => 1,
            'game_key' => 'fish1',
            'round_id' => 'test-round',
            'bet_amount' => '1.00',
            'payout_amount' => '0.00',
        ])->assertUnauthorized();
    }
}
