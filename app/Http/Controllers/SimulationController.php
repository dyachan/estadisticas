<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MatchSimulation;

class SimulationController extends Controller
{
    public function play(Request $request)
    {
        $result = self::runSimulation($request->teamA, $request->teamB);

        return response()->json($result);
    }

    /**
     * Run a full match simulation and return tickHistoric + summary.
     */
    public static function runSimulation(array $teamA, array $teamB): array
    {
        $match = app(MatchSimulation::class);
        $match->loadTeams($teamA, $teamB);

        for ($i = 1; $i <= MatchSimulation::TICKS_PER_MATCH; $i++) {
            $match->update();
        }

        return [
            'match'   => $match->tickHistoric,
            'summary' => $match->getSummary(),
        ];
    }
}
