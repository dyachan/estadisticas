<?php

namespace App\Services;

use App\Models\Team;

class MatchmakingService
{
    /**
     * Find the best available opponent for the given team.
     *
     * Priority (ascending):
     *   1. Closest losses count
     *   2. Closest matches_played count
     *   3. Closest wins count
     *   4. Closest draws count
     *
     * Excludes only the team itself.
     */
    public function findOpponent(Team $team): ?Team
    {
        return Team::where('id', '!=', $team->id)
            ->whereNotNull('configuration')
            ->get()
            ->sortBy([
                fn($a, $b) => abs($a->losses - $team->losses) <=> abs($b->losses - $team->losses),
                fn($a, $b) => abs($a->matches_played - $team->matches_played) <=> abs($b->matches_played - $team->matches_played),
                fn($a, $b) => abs($a->wins - $team->wins) <=> abs($b->wins - $team->wins),
                fn($a, $b) => abs($a->draws - $team->draws) <=> abs($b->draws - $team->draws),
            ])
            ->first();
    }
}
