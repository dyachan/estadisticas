<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryMatch extends Model
{
    protected $fillable = [
        'home_team_id', 'away_team_id',
        'home_strategy_id', 'away_strategy_id',
        'played_at', 'home_score', 'away_score', 'replay',
    ];

    protected $casts = [
        'played_at' => 'datetime',
        'replay'    => 'array',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'away_team_id');
    }

    public function homeStrategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class, 'home_strategy_id');
    }

    public function awayStrategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class, 'away_strategy_id');
    }
}
