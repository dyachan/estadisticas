<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\MatchSimulation;

class MatchController extends Controller
{
    public function play(Request $request){
        $match = new MatchSimulation();
        $match->loadTeams($request->teamA, $request->teamB);

        for ($i = 1; $i <= 5000; $i++) {
            $match->update();
        }

        return response()->json([
            "match" => $match->tickHistoric,
            "summary" => $match->getSummary()
        ]);
    }
}
