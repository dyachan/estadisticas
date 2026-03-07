<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GamePlayer extends Model
{
    public const UPGRADEABLE_ATTRIBUTES = [
        'max_speed', 'accuracy', 'control', 'reaction',
        'dribble', 'strength', 'endurance',
        'scan_with_ball', 'scan_without_ball',
    ];

    public const UPGRADE_DELTA = 0.05;

    protected $fillable = [
        'name',
        'max_speed', 'accuracy', 'control', 'reaction',
        'dribble', 'strength', 'endurance',
        'scan_with_ball', 'scan_without_ball',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(PlayerSnapshot::class)->orderByDesc('recorded_at');
    }

    /** Active team memberships (left_at IS NULL) */
    public function activeTeams(): BelongsToMany
    {
        return $this->belongsToMany(GameTeam::class, 'game_team_players')
                    ->wherePivotNull('left_at')
                    ->withPivot('position_index', 'joined_at');
    }

    /** Record a full attribute snapshot after any change */
    public function recordSnapshot(): void
    {
        $this->snapshots()->create([
            'max_speed'         => $this->max_speed,
            'accuracy'          => $this->accuracy,
            'control'           => $this->control,
            'reaction'          => $this->reaction,
            'dribble'           => $this->dribble,
            'strength'          => $this->strength,
            'endurance'         => $this->endurance,
            'scan_with_ball'    => $this->scan_with_ball,
            'scan_without_ball' => $this->scan_without_ball,
        ]);
    }

    /** Reconstruct attribute state at a given date from snapshots */
    public function stateAt(string $date): ?PlayerSnapshot
    {
        return $this->snapshots()
                    ->where('recorded_at', '<=', $date)
                    ->orderByDesc('recorded_at')
                    ->first();
    }
}
