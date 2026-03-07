<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use App\Http\Resources\TeamResource;

class TeamController extends Controller
{
    /**
     * Create a new simulation team with exactly 3 players in the configuration JSON.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                                           => 'required|string|max:255|unique:teams,name',
            'user_id'                                        => 'nullable|integer',
            'configuration'                                  => 'required|array|size:3',
            'configuration.*.name'                           => 'required|string|max:255',
            'configuration.*.default_zone_x'                 => 'required|numeric|min:0|max:100',
            'configuration.*.default_zone_y'                 => 'required|numeric|min:0|max:100',
            'configuration.*.rules_with_ball'                => 'nullable|array',
            'configuration.*.rules_with_ball.*.condition'    => 'required|integer',
            'configuration.*.rules_with_ball.*.action'       => 'required|integer',
            'configuration.*.rules_without_ball'             => 'nullable|array',
            'configuration.*.rules_without_ball.*.condition' => 'required|integer',
            'configuration.*.rules_without_ball.*.action'    => 'required|integer',
        ]);

        // Apply attribute defaults for any omitted stats
        $configuration = collect($validated['configuration'])->map(fn($p) => array_merge([
            'max_speed'          => 0.5,
            'accuracy'           => 0.5,
            'control'            => 0.5,
            'reaction'           => 0.5,
            'dribble'            => 0.5,
            'strength'           => 0.5,
            'endurance'          => 0.5,
            'scan_with_ball'     => null,
            'scan_without_ball'  => null,
            'rules_with_ball'    => [],
            'rules_without_ball' => [],
        ], $p))->values()->all();

        $team = Team::create([
            'name'          => $validated['name'],
            'user_id'       => $validated['user_id'] ?? null,
            'configuration' => $configuration,
        ]);

        return (new TeamResource($team))->response()->setStatusCode(201);
    }

    /**
     * Get a simulation team.
     */
    public function show(Team $team)
    {
        return new TeamResource($team);
    }

    /**
     * List all simulation teams (those with a configuration).
     */
    public function get()
    {
        return TeamResource::collection(Team::whereNotNull('configuration')->get());
    }

    /**
     * Upgrade one attribute of one player in the configuration JSON.
     */
    public function upgrade(Request $request, Team $team)
    {
        $validated = $request->validate([
            'position_index' => 'required|integer|min:0|max:2',
            'attribute'      => 'required|string|in:' . implode(',', Team::UPGRADEABLE_ATTRIBUTES),
        ]);

        $config = $team->configuration ?? [];
        $idx    = $validated['position_index'];

        if (!isset($config[$idx])) {
            return response()->json(['error' => 'Player not found at position ' . $idx], 404);
        }

        $attr              = $validated['attribute'];
        $current           = $config[$idx][$attr] ?? 0.5;
        $config[$idx][$attr] = min(1.0, round($current + Team::UPGRADE_DELTA, 4));

        $team->configuration = $config;
        $team->save();

        return response()->json(['success' => true, 'new_value' => $config[$idx][$attr]]);
    }

    /**
     * Assign a rule to a player slot in the configuration JSON.
     * Replaces whatever rule was at the given priority (0-based).
     */
    public function assignRule(Request $request, Team $team)
    {
        $validated = $request->validate([
            'position_index' => 'required|integer|min:0|max:2',
            'slot'           => 'required|string|in:with_ball,without_ball',
            'priority'       => 'required|integer|min:0',
            'condition'      => 'required|integer',
            'action'         => 'required|integer',
        ]);

        $config = $team->configuration ?? [];
        $idx    = $validated['position_index'];

        if (!isset($config[$idx])) {
            return response()->json(['error' => 'Player not found at position ' . $idx], 404);
        }

        $slotKey                                       = $validated['slot'] === 'with_ball'
            ? 'rules_with_ball' : 'rules_without_ball';
        $config[$idx][$slotKey][$validated['priority']] = [
            'condition' => $validated['condition'],
            'action'    => $validated['action'],
        ];

        // Re-index to keep a clean sequential array
        $config[$idx][$slotKey] = array_values($config[$idx][$slotKey]);

        $team->configuration = $config;
        $team->save();

        return response()->json([
            'success' => true,
            'rules'   => $config[$idx][$slotKey],
        ]);
    }
}
