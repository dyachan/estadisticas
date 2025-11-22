<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use App\Http\Resources\TeamResource;

class TeamController extends Controller
{
    /**
     * List all teams.
     */
    public function get()
    {
        return TeamResource::collection(Team::all());
    }
    
    /**
     * Store a newly created Team.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'formation' => 'required|string'
        ]);

        if(Team::where("name", $validated["name"])->exists()){
            return response()->json([
                'success' => false,
                'error'    => "Name already exists"
            ]);
        }

        $team = Team::create($validated);

        return response()->json([
            'success' => true
        ], 201);
    }
}