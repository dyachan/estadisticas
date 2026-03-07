<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTeamPlayer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'game_team_id', 'game_player_id', 'position_index', 'joined_at', 'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at'   => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'game_team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
