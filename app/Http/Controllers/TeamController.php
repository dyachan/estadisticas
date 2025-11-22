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
        // Validación
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'formation' => 'required|string'
        ]);

        // Crear equipo
        $team = Team::create($validated);

        // Respuesta
        return response()->json([
            'success' => true,
            'team'    => $team
        ], 201);
    }
}