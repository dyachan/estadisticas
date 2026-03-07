<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameTeam extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All player memberships (active + past) */
    public function playerMemberships(): HasMany
    {
        return $this->hasMany(GameTeamPlayer::class);
    }

    /** Currently active players (left_at IS NULL) */
    public function activePlayers(): BelongsToMany
    {
        return $this->belongsToMany(GamePlayer::class, 'game_team_players')
                    ->wherePivotNull('left_at')
                    ->withPivot('position_index', 'joined_at')
                    ->orderByPivot('position_index');
    }

    public function strategies(): HasMany
    {
        return $this->hasMany(Strategy::class)->orderByDesc('created_at');
    }

    /** The currently active strategy (most recent) */
    public function currentStrategy(): ?Strategy
    {
        return $this->strategies()->first();
    }

    /** Strategy active at a given date */
    public function strategyAt(string $date): ?Strategy
    {
        return $this->strategies()
                    ->where('created_at', '<=', $date)
                    ->first();
    }
}
