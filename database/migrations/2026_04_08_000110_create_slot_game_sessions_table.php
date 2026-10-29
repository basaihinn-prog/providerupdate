<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 80)->unique();
            $table->string('player_identifier', 100);
            $table->string('status', 20)->default('active');
            $table->string('client_seed', 120)->nullable();
            $table->unsignedInteger('free_spins_remaining')->default(0);
            $table->unsignedInteger('free_spins_awarded')->default(0);
            $table->decimal('free_spin_multiplier', 8, 2)->default(1);
            $table->decimal('total_bet', 15, 2)->default(0);
            $table->decimal('total_win', 15, 2)->default(0);
            $table->decimal('ggr', 15, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['game_instance_id', 'player_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_game_sessions');
    }
};
