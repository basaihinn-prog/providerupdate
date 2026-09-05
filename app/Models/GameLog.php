<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLog extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'game_type',
        'score',
        'kills',
        'hits_taken',
        'final_balance',
        'game_duration',
        'game_result',
        'timestamp'
    ];
    
    protected $casts = [
        'timestamp' => 'datetime',
        'final_balance' => 'decimal:2',
        'game_duration' => 'decimal:2'
    ];
    
    /**
     * Get the user that owns the game log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}