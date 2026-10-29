<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_instance_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('spin_count')->default(0);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->decimal('total_bet', 18, 2)->default(0);
            $table->decimal('total_win', 18, 2)->default(0);
            $table->decimal('ggr', 18, 2)->default(0);
            $table->decimal('observed_rtp', 8, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('last_spin_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_metrics');
    }
};
