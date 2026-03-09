<?php

namespace App\Http\Controllers;

use App\Models\GameTeam;
use App\Models\GameTeamPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameFormationController extends Controller
{
    /**
     * Create or update the current formation for a user.
     * If the user already has a team with the given name it is reused;
     * otherwise a new GameTeam is created.
     * Active player slots are replaced: previous active memberships are
     * closed (left_at = now) and the new lineup is inserted.
     *
     * POST /game/formation
     * Body: { user_id, name, player_ids: [id, id, id] }
     */
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'name'         => 'required|string|max:255',
            'player_ids'   => 'required|array|size:3',
            'player_ids.*' => 'required|integer|exists:game_players,id',
        ]);

        DB::transaction(function () use ($validated, &$team) {
            $team = GameTeam::firstOrCreate(
                ['user_id' => $validated['user_id'], 'name' => $validated['name']],
                ['user_id' => $validated['user_id'], 'name' => $validated['name']]
            );

            // Close all current active memberships
            GameTeamPlayer::where('game_team_id', $team->id)
                ->whereNull('left_at')
                ->update(['left_at' => now()]);

            // Insert new lineup
            foreach ($validated['player_ids'] as $index => $playerId) {
                GameTeamPlayer::create([
                    'game_team_id'   => $team->id,
                    'game_player_id' => $playerId,
                    'position_index' => $index,
                    'joined_at'      => now(),
                    'left_at'        => null,
                ]);
            }
        });

        return response()->json($team->load('activePlayers'), 200);
    }
}
