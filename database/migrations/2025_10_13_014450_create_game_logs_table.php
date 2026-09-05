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
        Schema::create('game_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('game_type', 50);
            $table->integer('score')->default(0);
            $table->integer('kills')->default(0);
            $table->integer('hits_taken')->default(0);
            $table->decimal('final_balance', 5, 2)->default(0);
            $table->decimal('game_duration', 8, 2)->default(0);
            $table->enum('game_result', ['game_over', 'victory', 'quit'])->default('game_over');
            $table->timestamp('timestamp');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['game_type', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_logs');
    }
};
