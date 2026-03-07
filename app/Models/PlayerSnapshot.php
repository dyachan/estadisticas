<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'game_player_id',
        'max_speed', 'accuracy', 'control', 'reaction',
        'dribble', 'strength', 'endurance',
        'scan_with_ball', 'scan_without_ball',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
