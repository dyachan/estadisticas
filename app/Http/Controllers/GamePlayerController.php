<?php

namespace App\Http\Controllers;

use App\Models\GamePlayer;
use Illuminate\Http\Request;

class GamePlayerController extends Controller
{
    /**
     * Create a new game player with all attributes at 0.1.
     *
     * POST /game/players
     * Body: { name }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $defaults = array_fill_keys(GamePlayer::UPGRADEABLE_ATTRIBUTES, 0.1);

        $player = GamePlayer::create(array_merge(['name' => $validated['name']], $defaults));

        return response()->json($player, 201);
    }

    /**
     * Update one or more attributes of a game player. Records a snapshot after saving.
     *
     * PUT /game/players/{player}
     * Body: { max_speed?, accuracy?, control?, reaction?, dribble?, strength?, endurance?, scan_with_ball?, scan_without_ball? }
     */
    public function update(Request $request, GamePlayer $player)
    {
        $attrList = implode(',', GamePlayer::UPGRADEABLE_ATTRIBUTES);

        $validated = $request->validate([
            'max_speed'         => "nullable|numeric|min:0|max:1",
            'accuracy'          => "nullable|numeric|min:0|max:1",
            'control'           => "nullable|numeric|min:0|max:1",
            'reaction'          => "nullable|numeric|min:0|max:1",
            'dribble'           => "nullable|numeric|min:0|max:1",
            'strength'          => "nullable|numeric|min:0|max:1",
            'endurance'         => "nullable|numeric|min:0|max:1",
            'scan_with_ball'    => "nullable|numeric|min:0|max:1",
            'scan_without_ball' => "nullable|numeric|min:0|max:1",
        ]);

        $updates = array_filter($validated, fn($v) => !is_null($v));

        if (empty($updates)) {
            return response()->json(['error' => 'No attributes provided.'], 422);
        }

        $player->fill($updates)->save();
        $player->recordSnapshot();

        return response()->json($player);
    }
}
