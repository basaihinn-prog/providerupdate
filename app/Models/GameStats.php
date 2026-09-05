<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStats extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'total_score',
        'total_kills',
        'games_played',
        'best_score',
        'average_score'
    ];
    
    protected $casts = [
        'total_score' => 'integer',
        'total_kills' => 'integer',
        'games_played' => 'integer',
        'best_score' => 'integer',
        'average_score' => 'decimal:2'
    ];
    
    /**
     * Get the user that owns the game stats
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}