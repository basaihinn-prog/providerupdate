<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fish_rtp_configs', function (Blueprint $table) {
            $table->id();
            $table->string('game_key', 100)->unique();
            // Basis points: 9600 = 96.00%. Reporting/configuration only;
            // this value must not be used to force or alter individual outcomes.
            $table->unsignedInteger('target_rtp_bps')->default(9600);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('fish_rtp_config_audits', function (Blueprint $table) {
            $table->id();
            $table->string('game_key', 100)->index();
            $table->unsignedInteger('from_rtp_bps')->nullable();
            $table->unsignedInteger('to_rtp_bps');
            $table->boolean('from_enabled')->nullable();
            $table->boolean('to_enabled');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['game_key', 'created_at'], 'fish_rtp_audit_game_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_rtp_config_audits');
        Schema::dropIfExists('fish_rtp_configs');
    }
};
