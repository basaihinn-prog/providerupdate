<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_instances', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name', 50);
            $table->string('game_code', 40)->unique();
            $table->string('display_name', 120);
            $table->string('api_key_hash', 128);
            $table->string('api_key_last4', 4);
            $table->decimal('rtp_target', 5, 2)->default(96.50);
            $table->string('volatility', 20)->default('medium');
            $table->boolean('is_active')->default(true);
            $table->boolean('test_mode')->default(false);
            $table->boolean('allow_manual_bonus')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_instances');
    }
};
