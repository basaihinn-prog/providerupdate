<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpinHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_instance_id',
        'slot_game_session_id',
        'user_id',
        'match_no',
        'bet_amount',
        'base_win_amount',
        'scatter_win_amount',
        'bonus_win_amount',
        'total_win_amount',
        'balance_before',
        'balance_after',
        'ggr_delta',
        'is_free_spin',
        'server_seed_hash',
        'server_seed',
        'client_seed',
        'nonce',
        'grid',
        'wins',
        'feature_state',
        'meta',
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'base_win_amount' => 'decimal:2',
        'scatter_win_amount' => 'decimal:2',
        'bonus_win_amount' => 'decimal:2',
        'total_win_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'ggr_delta' => 'decimal:2',
        'is_free_spin' => 'boolean',
        'grid' => 'array',
        'wins' => 'array',
        'feature_state' => 'array',
        'meta' => 'array',
    ];

    public function gameInstance(): BelongsTo
    {
        return $this->belongsTo(GameInstance::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SlotGameSession::class, 'slot_game_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
