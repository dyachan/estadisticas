<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use App\Services\MatchSimulation;
use App\Services\MatchmakingService;

class MatchController extends Controller
{
    public function __construct(private MatchmakingService $matchmaking) {}

    /**
     * Find an opponent via matchmaking, run the simulation, record the result,
     * and update the initiating team's counters.
     */
    public function play(Request $request)
    {
        $request->validate(['team_id' => 'required|integer|exists:teams,id']);

        $team = Team::findOrFail($request->team_id);

        $opponent = $this->matchmaking->findOpponent($team);

        if (!$opponent) {
            return response()->json(['error' => 'No opponent found'], 404);
        }

        // Run simulation (team is always Team A)
        $simulation = app(MatchSimulation::class);
        $simulation->loadTeams($team->toSimulationFormat(), $opponent->toSimulationFormat());

        for ($i = 1; $i <= MatchSimulation::TICKS_PER_MATCH; $i++) {
            $simulation->update();
        }

        $summary = $simulation->getSummary();
        $goalsFor     = (int) $summary['GoalsA'];
        $goalsAgainst = (int) $summary['GoalsB'];
        $result = match(true) {
            $goalsFor > $goalsAgainst => 'win',
            $goalsFor < $goalsAgainst => 'loss',
            default                   => 'draw',
        };

        // Persist match record
        GameMatch::create([
            'team_id'           => $team->id,
            'opponent_snapshot' => $opponent->toSnapshot(),
            'goals_for'         => $goalsFor,
            'goals_against'     => $goalsAgainst,
            'result'            => $result,
        ]);

        // Update initiating team counters
        $counterColumn = match($result) {
            'win'  => 'wins',
            'loss' => 'losses',
            'draw' => 'draws',
        };
        $team->matches_played += 1;
        $team->$counterColumn += 1;
        $team->save();

        return response()->json([
            'result'         => $result,
            'goals_for'      => $goalsFor,
            'goals_against'  => $goalsAgainst,
            'opponent'       => ['id' => $opponent->id, 'name' => $opponent->name],
            'team'           => [
                'wins'           => $team->wins,
                'draws'          => $team->draws,
                'losses'         => $team->losses,
                'matches_played' => $team->matches_played,
            ],
            'match'          => $simulation->tickHistoric,
            'summary'        => $summary,
        ]);
    }

    /**
     * Get match history for a team.
     */
    public function history(Team $team)
    {
        $matches = $team->gameMatches()
            ->get(['id', 'opponent_snapshot', 'goals_for', 'goals_against', 'result', 'created_at'])
            ->map(fn($m) => [
                'id'            => $m->id,
                'opponent_name' => $m->opponent_snapshot['name'] ?? null,
                'goals_for'     => $m->goals_for,
                'goals_against' => $m->goals_against,
                'result'        => $m->result,
                'played_at'     => $m->created_at,
            ]);

        return response()->json($matches);
    }
}
