<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_instance_id',
        'spin_count',
        'hit_count',
        'total_bet',
        'total_win',
        'ggr',
        'observed_rtp',
        'meta',
        'last_spin_at',
    ];

    protected $casts = [
        'spin_count' => 'integer',
        'hit_count' => 'integer',
        'total_bet' => 'decimal:2',
        'total_win' => 'decimal:2',
        'ggr' => 'decimal:2',
        'observed_rtp' => 'decimal:4',
        'meta' => 'array',
        'last_spin_at' => 'datetime',
    ];

    public function gameInstance(): BelongsTo
    {
        return $this->belongsTo(GameInstance::class);
    }
}
