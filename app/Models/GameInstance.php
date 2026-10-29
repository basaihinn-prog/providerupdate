<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_name',
        'game_code',
        'display_name',
        'api_key_hash',
        'api_key_last4',
        'rtp_target',
        'volatility',
        'is_active',
        'test_mode',
        'allow_manual_bonus',
        'config',
    ];

    protected $casts = [
        'rtp_target' => 'decimal:2',
        'is_active' => 'boolean',
        'test_mode' => 'boolean',
        'allow_manual_bonus' => 'boolean',
        'config' => 'array',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(SlotGameSession::class);
    }

    public function spins(): HasMany
    {
        return $this->hasMany(SpinHistory::class);
    }

    public function metric(): HasOne
    {
        return $this->hasOne(GameMetric::class);
    }
}
