<?php

namespace App\Http\Controllers;

use App\Models\Tactic;
use Illuminate\Http\Request;

class GameTacticController extends Controller
{
    /**
     * Save a named tactic (zone + rules) for a user.
     *
     * POST /game/tactics
     * Body:
     * {
     *   user_id,
     *   name,
     *   default_zone_x?,
     *   default_zone_y?,
     *   with_ball?:    [ { condition, action }, ... ],
     *   without_ball?: [ { condition, action }, ... ]
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'                       => 'required|integer|exists:users,id',
            'name'                          => 'required|string|max:255',
            'default_zone_x'                => 'nullable|numeric|min:0|max:100',
            'default_zone_y'                => 'nullable|numeric|min:0|max:100',
            'with_ball'                     => 'nullable|array',
            'with_ball.*.condition'         => 'required|integer',
            'with_ball.*.action'            => 'required|integer',
            'without_ball'                  => 'nullable|array',
            'without_ball.*.condition'      => 'required|integer',
            'without_ball.*.action'         => 'required|integer',
        ]);

        $tactic = Tactic::create([
            'user_id'            => $validated['user_id'],
            'name'               => $validated['name'],
            'default_zone_x'     => $validated['default_zone_x'] ?? null,
            'default_zone_y'     => $validated['default_zone_y'] ?? null,
            'rules_with_ball'    => $validated['with_ball']    ?? [],
            'rules_without_ball' => $validated['without_ball'] ?? [],
        ]);

        return response()->json($tactic, 201);
    }
}
