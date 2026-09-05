<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('total_score')->default(0);
            $table->integer('total_kills')->default(0);
            $table->integer('games_played')->default(0);
            $table->integer('best_score')->default(0);
            $table->decimal('average_score', 8, 2)->default(0);
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index('total_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stats');
    }
};
