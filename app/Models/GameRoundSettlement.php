<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRoundSettlement extends Model
{
    protected $fillable = [
        'provider',
        'round_id',
        'user_id',
        'game_code',
        'bet_amount',
        'win_amount',
        'net_amount',
        'balance_before',
        'balance_after',
        'request_hash',
        'status',
        'meta',
        'response',
    ];

    protected $casts = [
        'bet_amount' => 'decimal:2',
        'win_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
        'response' => 'array',
    ];
}
