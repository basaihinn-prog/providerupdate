<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotGameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_instance_id',
        'user_id',
        'session_token',
        'player_identifier',
        'status',
        'client_seed',
        'free_spins_remaining',
        'free_spins_awarded',
        'free_spin_multiplier',
        'total_bet',
        'total_win',
        'ggr',
        'meta',
        'last_activity_at',
    ];

    protected $casts = [
        'free_spins_remaining' => 'integer',
        'free_spins_awarded' => 'integer',
        'free_spin_multiplier' => 'decimal:2',
        'total_bet' => 'decimal:2',
        'total_win' => 'decimal:2',
        'ggr' => 'decimal:2',
        'meta' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function gameInstance(): BelongsTo
    {
        return $this->belongsTo(GameInstance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spins(): HasMany
    {
        return $this->hasMany(SpinHistory::class, 'slot_game_session_id');
    }
}
