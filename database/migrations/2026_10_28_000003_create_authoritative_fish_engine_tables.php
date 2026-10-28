<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fish_engine_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->string('game_key', 100);
            $table->string('engine_version', 80);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('sequence')->default(0);
            $table->json('simulation_state');
            $table->timestamp('last_command_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['player_id', 'game_key', 'status']);
        });

        Schema::create('fish_engine_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('fish_engine_sessions')->cascadeOnDelete();
            $table->string('command_id', 100);
            $table->string('command_type', 32);
            $table->unsignedBigInteger('sequence');
            $table->json('request_payload');
            $table->json('response_payload');
            $table->timestamps();
            $table->unique(['session_id', 'command_id']);
            $table->unique(['session_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_engine_commands');
        Schema::dropIfExists('fish_engine_sessions');
    }
};
