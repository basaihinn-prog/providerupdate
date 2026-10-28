<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fish_game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('users')->restrictOnDelete();
            $table->string('game_key', 100);
            $table->string('round_id', 160);
            $table->char('currency', 3)->default('MMK');
            $table->decimal('bet_amount', 18, 2);
            $table->decimal('payout_amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at');
            $table->timestamps();
            $table->unique(['game_key', 'round_id'], 'fish_round_game_round_unique');
            $table->index(['game_key', 'settled_at'], 'fish_round_game_settled_idx');
            $table->index(['player_id', 'settled_at'], 'fish_round_player_settled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_game_rounds');
    }
};
