<?php

namespace Database\Seeders;

use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GameTeamPlayer;
use App\Models\GameTeamUi;
use App\Models\Strategy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds roguelike teams and their strategies from roguelike_seed_data.json.
 * The JSON was exported from a live DB; this seeder re-creates every record
 * with fresh IDs and fixes all internal references so they stay consistent.
 */
class RoguelikeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/roguelike_seed_data.json');
        $data = json_decode(file_get_contents($path), true);

        // old game_team_id  → new game_team_id
        $teamIdMap   = [];
        // old game_player_id → new game_player_id
        $playerIdMap = [];

        DB::transaction(function () use ($data, &$teamIdMap, &$playerIdMap) {

            foreach ($data['teams'] as $teamData) {
                $team = GameTeam::create([
                    'user_id'        => null,
                    'name'           => $teamData['name'],
                    'wins'           => $teamData['wins'],
                    'draws'          => $teamData['draws'],
                    'losses'         => $teamData['losses'],
                    'matches_played' => $teamData['matches_played'],
                ]);

                $teamIdMap[$teamData['id']] = $team->id;

                // UI / color
                $color = $teamData['color'] ?? ($teamData['ui']['color'] ?? '#fd9946');
                GameTeamUi::create([
                    'game_team_id' => $team->id,
                    'color'        => $color,
                ]);

                // Players (active_players from the eager-loaded relation)
                foreach ($teamData['active_players'] as $playerData) {
                    $oldPlayerId = $playerData['id'];

                    // A player may be shared across teams in the original DB; only create once.
                    if (!isset($playerIdMap[$oldPlayerId])) {
                        $player = GamePlayer::create([
                            'name'              => $playerData['name'],
                            'max_speed'         => $playerData['max_speed'],
                            'accuracy'          => $playerData['accuracy'],
                            'control'           => $playerData['control'],
                            'reaction'          => $playerData['reaction'],
                            'dribble'           => $playerData['dribble'],
                            'strength'          => $playerData['strength'],
                            'endurance'         => $playerData['endurance'],
                            'scan_with_ball'    => $playerData['scan_with_ball'],
                            'scan_without_ball' => $playerData['scan_without_ball'],
                        ]);
                        $playerIdMap[$oldPlayerId] = $player->id;
                    }

                    GameTeamPlayer::create([
                        'game_team_id'   => $team->id,
                        'game_player_id' => $playerIdMap[$oldPlayerId],
                        'position_index' => $playerData['pivot']['position_index'],
                        'joined_at'      => $playerData['pivot']['joined_at'],
                    ]);
                }
            }

            // Strategies – re-map game_team_id and game_player_id inside formation JSON
            foreach ($data['strategies'] as $stratData) {
                $oldTeamId = $stratData['game_team_id'];

                if (!isset($teamIdMap[$oldTeamId])) {
                    continue; // skip orphaned strategies
                }

                $formation = collect($stratData['formation'])->map(function ($p) use ($playerIdMap) {
                    $oldPid = $p['game_player_id'];
                    $p['game_player_id'] = $playerIdMap[$oldPid] ?? $oldPid;
                    return $p;
                })->values()->all();

                Strategy::create([
                    'game_team_id'   => $teamIdMap[$oldTeamId],
                    'formation'      => $formation,
                    'wins'           => $stratData['wins'],
                    'draws'          => $stratData['draws'],
                    'losses'         => $stratData['losses'],
                    'matches_played' => $stratData['matches_played'],
                ]);
            }
        });
    }
}
