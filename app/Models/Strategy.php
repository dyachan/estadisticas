<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Strategy extends Model
{
    protected $fillable = ['game_team_id', 'formation', 'wins', 'draws', 'losses', 'matches_played'];

    protected $casts = ['formation' => 'array'];

    public function gameTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class);
    }

    public function playerTactics(): HasMany
    {
        return $this->hasMany(StrategyPlayerTactic::class);
    }
}
