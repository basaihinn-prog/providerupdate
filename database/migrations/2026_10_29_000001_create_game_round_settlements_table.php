<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_round_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('round_id', 128);
            $table->unsignedBigInteger('user_id')->index();
            $table->string('game_code', 100);
            $table->decimal('bet_amount', 18, 2);
            $table->decimal('win_amount', 18, 2);
            $table->decimal('net_amount', 18, 2);
            $table->decimal('balance_before', 18, 2)->nullable();
            $table->decimal('balance_after', 18, 2)->nullable();
            $table->char('request_hash', 64);
            $table->string('status', 24)->default('processing');
            $table->json('meta')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'round_id'], 'game_round_settlements_provider_round_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_round_settlements');
    }
};
