<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('transaction_id', 128);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('game_id')->nullable();
            $table->string('round_id')->nullable();
            $table->decimal('amount', 64, 2);
            $table->decimal('before_balance', 64, 2);
            $table->decimal('after_balance', 64, 2);
            $table->string('reason')->nullable();
            $table->boolean('is_end')->default(false);
            $table->json('raw_request')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'transaction_id'], 'provider_wallet_tx_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['game_id', 'round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallet_transactions');
    }
};
