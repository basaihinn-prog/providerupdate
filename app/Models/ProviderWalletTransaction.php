<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderWalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'transaction_id',
        'user_id',
        'game_id',
        'round_id',
        'amount',
        'before_balance',
        'after_balance',
        'reason',
        'is_end',
        'raw_request',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'before_balance' => 'decimal:2',
        'after_balance' => 'decimal:2',
        'is_end' => 'boolean',
        'raw_request' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
