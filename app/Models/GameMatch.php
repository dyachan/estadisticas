<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    protected $fillable = [
        'team_id',
        'opponent_snapshot',
        'goals_for',
        'goals_against',
        'result',
    ];

    protected $casts = [
        'opponent_snapshot' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
