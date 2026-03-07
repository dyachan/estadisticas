<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyPlayerTactic extends Model
{
    public $timestamps = false;

    protected $fillable = ['strategy_id', 'game_player_id', 'tactic_id'];

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    public function tactic(): BelongsTo
    {
        return $this->belongsTo(Tactic::class);
    }
}
