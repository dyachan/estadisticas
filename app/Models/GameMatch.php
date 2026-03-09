<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    protected $fillable = [
        'team_id',
        'home_snapshot',
        'opponent_snapshot',
        'goals_for',
        'goals_against',
        'result',
        'replay',
    ];

    protected $casts = [
        'home_snapshot'     => 'array',
        'opponent_snapshot' => 'array',
        'replay'            => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
