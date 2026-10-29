<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_game_session_id')->constrained('slot_game_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_no', 40)->unique();
            $table->decimal('bet_amount', 15, 2)->default(0);
            $table->decimal('base_win_amount', 15, 2)->default(0);
            $table->decimal('scatter_win_amount', 15, 2)->default(0);
            $table->decimal('bonus_win_amount', 15, 2)->default(0);
            $table->decimal('total_win_amount', 15, 2)->default(0);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->decimal('ggr_delta', 15, 2)->default(0);
            $table->boolean('is_free_spin')->default(false);
            $table->string('server_seed_hash', 128);
            $table->string('server_seed', 128);
            $table->string('client_seed', 120)->nullable();
            $table->unsignedBigInteger('nonce')->default(0);
            $table->json('grid');
            $table->json('wins')->nullable();
            $table->json('feature_state')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['game_instance_id', 'created_at']);
            $table->index(['slot_game_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_histories');
    }
};
