<?php

namespace App\Http\Controllers;

use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GameTeamPlayer;
use App\Models\Strategy;
use App\Models\StoryMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoguelikeController extends Controller
{
    /**
     * Start a new roguelike run.
     * Creates 3 GamePlayers (all stats 0.1) and a GameTeam.
     *
     * POST /roguelike/start
     * Body: { user_id?, name?, player_names?: [str, str, str] }
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id'        => 'nullable|integer|exists:users,id',
            'name'           => 'nullable|string|max:255',
            'player_names'   => 'nullable|array|size:3',
            'player_names.*' => 'nullable|string|max:255',
        ]);

        $team    = null;
        $players = [];

        DB::transaction(function () use ($validated, &$team, &$players) {
            $team = GameTeam::create([
                'user_id' => $validated['user_id'] ?? null,
                'name'    => $validated['name'] ?? ('rogue_' . time()),
            ]);

            $defaults = array_fill_keys(GamePlayer::UPGRADEABLE_ATTRIBUTES, 0.1);

            for ($i = 0; $i < 3; $i++) {
                $player = GamePlayer::create(array_merge(
                    ['name' => $validated['player_names'][$i] ?? "Player {$i}"],
                    $defaults
                ));

                $player->recordSnapshot();

                GameTeamPlayer::create([
                    'game_team_id'   => $team->id,
                    'game_player_id' => $player->id,
                    'position_index' => $i,
                    'joined_at'      => now(),
                ]);

                $players[] = $player;
            }
        });

        return response()->json([
            'game_team_id' => $team->id,
            'players'      => $players,
        ], 201);
    }

    /**
     * Play a roguelike turn.
     * Updates player stats, saves the strategy (formation), finds a real opponent
     * or falls back to a CPU, runs the match, persists the result.
     *
     * POST /roguelike/play
     * Body: {
     *   game_team_id,
     *   players: [{ game_player_id, default_zone_x, default_zone_y,
     *               rules_with_ball, rules_without_ball,
     *               max_speed?, accuracy?, control?, reaction?,
     *               dribble?, strength?, endurance?,
     *               scan_with_ball?, scan_without_ball? }]
     * }
     */
    public function play(Request $request)
    {
        $validated = $request->validate([
            'game_team_id'                              => 'required|integer|exists:game_teams,id',
            'players'                                   => 'required|array|size:3',
            'players.*.game_player_id'                  => 'required|integer|exists:game_players,id',
            'players.*.default_zone_x'                  => 'required|numeric|min:0|max:100',
            'players.*.default_zone_y'                  => 'required|numeric|min:0|max:100',
            'players.*.rules_with_ball'                 => 'nullable|array',
            'players.*.rules_with_ball.*.condition'     => 'required|integer',
            'players.*.rules_with_ball.*.action'        => 'required|integer',
            'players.*.rules_without_ball'              => 'nullable|array',
            'players.*.rules_without_ball.*.condition'  => 'required|integer',
            'players.*.rules_without_ball.*.action'     => 'required|integer',
            'players.*.max_speed'                       => 'nullable|numeric|min:0|max:1',
            'players.*.accuracy'                        => 'nullable|numeric|min:0|max:1',
            'players.*.control'                         => 'nullable|numeric|min:0|max:1',
            'players.*.reaction'                        => 'nullable|numeric|min:0|max:1',
            'players.*.dribble'                         => 'nullable|numeric|min:0|max:1',
            'players.*.strength'                        => 'nullable|numeric|min:0|max:1',
            'players.*.endurance'                       => 'nullable|numeric|min:0|max:1',
            'players.*.scan_with_ball'                  => 'nullable|numeric|min:0|max:1',
            'players.*.scan_without_ball'               => 'nullable|numeric|min:0|max:1',
        ]);

        $team      = GameTeam::findOrFail($validated['game_team_id']);
        $ids       = collect($validated['players'])->pluck('game_player_id');
        $playerMap = GamePlayer::whereIn('id', $ids)->get()->keyBy('id');

        // 1. Update player stats + record snapshots
        foreach ($validated['players'] as $p) {
            $stats = array_filter([
                'max_speed'         => $p['max_speed']         ?? null,
                'accuracy'          => $p['accuracy']          ?? null,
                'control'           => $p['control']           ?? null,
                'reaction'          => $p['reaction']          ?? null,
                'dribble'           => $p['dribble']           ?? null,
                'strength'          => $p['strength']          ?? null,
                'endurance'         => $p['endurance']         ?? null,
                'scan_with_ball'    => $p['scan_with_ball']    ?? null,
                'scan_without_ball' => $p['scan_without_ball'] ?? null,
            ], fn($v) => !is_null($v));

            if (!empty($stats)) {
                $player = $playerMap[$p['game_player_id']];
                $player->fill($stats)->save();
                $player->recordSnapshot();
                $playerMap[$p['game_player_id']] = $player->fresh();
            }
        }

        // 2. Save strategy (denormalized formation blob)
        $formation = collect($validated['players'])->map(function ($p) use ($playerMap) {
            $gp = $playerMap[$p['game_player_id']];
            return [
                'game_player_id'    => $gp->id,
                'name'              => $gp->name,
                'default_zone_x'    => $p['default_zone_x'],
                'default_zone_y'    => $p['default_zone_y'],
                'rules_with_ball'   => $p['rules_with_ball']   ?? [],
                'rules_without_ball'=> $p['rules_without_ball'] ?? [],
                'max_speed'         => $gp->max_speed,
                'accuracy'          => $gp->accuracy,
                'control'           => $gp->control,
                'reaction'          => $gp->reaction,
                'dribble'           => $gp->dribble,
                'strength'          => $gp->strength,
                'endurance'         => $gp->endurance,
                'scan_with_ball'    => $gp->scan_with_ball,
                'scan_without_ball' => $gp->scan_without_ball,
            ];
        })->values()->all();

        $strategy = Strategy::create([
            'game_team_id' => $team->id,
            'formation'    => $formation,
        ]);

        // 3. Build home simulation format from formation
        $homePlayers = collect($formation)->map(fn($p) => [
            'name'            => $p['name'],
            'rules'           => [$p['rules_with_ball'], $p['rules_without_ball']],
            'defaultZone'     => ['x' => $p['default_zone_x'], 'y' => $p['default_zone_y']],
            'maxSpeed'        => $p['max_speed'],
            'accuracy'        => $p['accuracy'],
            'control'         => $p['control'],
            'reaction'        => $p['reaction'],
            'dribble'         => $p['dribble'],
            'strength'        => $p['strength'],
            'endurance'       => $p['endurance'],
            'scanWithBall'    => $p['scan_with_ball'],
            'scanWithoutBall' => $p['scan_without_ball'],
        ])->values()->all();

        // 4. Find real opponent GameTeam or fall back to CPU
        $opponentTeam = GameTeam::where('id', '!=', $team->id)
            ->where('matches_played', '>', 0)
            ->get()
            ->sortBy([
                fn($a, $b) => abs($a->losses - $team->losses)         <=> abs($b->losses - $team->losses),
                fn($a, $b) => abs($a->matches_played - $team->matches_played) <=> abs($b->matches_played - $team->matches_played),
                fn($a, $b) => abs($a->wins - $team->wins)             <=> abs($b->wins - $team->wins),
                fn($a, $b) => abs($a->draws - $team->draws)           <=> abs($b->draws - $team->draws),
            ])
            ->first();

        $opponentStrategy   = $opponentTeam?->currentStrategy();
        $opponentStrategyId = null;
        $opponentTeamId     = null;

        if ($opponentStrategy?->formation) {
            $opponentPlayers = collect($opponentStrategy->formation)->map(fn($p) => [
                'name'            => $p['name'],
                'rules'           => [$p['rules_with_ball'] ?? [], $p['rules_without_ball'] ?? []],
                'defaultZone'     => ['x' => $p['default_zone_x'], 'y' => $p['default_zone_y']],
                'maxSpeed'        => $p['max_speed'],
                'accuracy'        => $p['accuracy'],
                'control'         => $p['control'],
                'reaction'        => $p['reaction'],
                'dribble'         => $p['dribble'],
                'strength'        => $p['strength'],
                'endurance'       => $p['endurance'],
                'scanWithBall'    => $p['scan_with_ball'],
                'scanWithoutBall' => $p['scan_without_ball'],
            ])->values()->all();
            $opponentName       = $opponentTeam->name;
            $opponentTeamId     = $opponentTeam->id;
            $opponentStrategyId = $opponentStrategy->id;
        } else {
            // CPU fallback scaled by team's matches_played
            $cpuStat         = min(0.1 + $team->matches_played * 0.05, 0.8);
            $cpuDefaultZones = [['x' => 50, 'y' => 25], ['x' => 30, 'y' => 50], ['x' => 70, 'y' => 70]];
            $cpuRules        = [
                [['condition' => 1, 'action' => 9], ['condition' => 7, 'action' => 5]],
                [['condition' => 4, 'action' => 4], ['condition' => 5, 'action' => 2]],
            ];
            $opponentPlayers = collect(range(0, 2))->map(fn($i) => [
                'name'            => 'CPU ' . ['GK', 'DEF', 'FWD'][$i],
                'rules'           => $cpuRules,
                'defaultZone'     => $cpuDefaultZones[$i],
                'maxSpeed'        => $cpuStat, 'accuracy'        => $cpuStat,
                'control'         => $cpuStat, 'reaction'        => $cpuStat,
                'dribble'         => $cpuStat, 'strength'        => $cpuStat,
                'endurance'       => $cpuStat, 'scanWithBall'    => $cpuStat,
                'scanWithoutBall' => $cpuStat,
            ])->values()->all();
            $opponentName = 'CPU #' . ($team->matches_played + 1);
        }

        // 5. Run simulation
        $simResult    = SimulationController::runSimulation(
            ['players' => $homePlayers],
            ['players' => $opponentPlayers]
        );
        $summary      = $simResult['summary'];
        $goalsFor     = (int) $summary['GoalsA'];
        $goalsAgainst = (int) $summary['GoalsB'];
        $result       = match(true) {
            $goalsFor > $goalsAgainst => 'win',
            $goalsFor < $goalsAgainst => 'loss',
            default                   => 'draw',
        };

        // 6. Persist match record
        StoryMatch::create([
            'home_team_id'      => $team->id,
            'away_team_id'      => $opponentTeamId,
            'home_strategy_id'  => $strategy->id,
            'away_strategy_id'  => $opponentStrategyId,
            'home_score'        => $goalsFor,
            'away_score'        => $goalsAgainst,
            'replay'            => $simResult['match'],
        ]);

        // 7. Update team counters
        $counterColumn = match($result) { 'win' => 'wins', 'loss' => 'losses', 'draw' => 'draws' };
        $team->matches_played += 1;
        $team->$counterColumn += 1;
        $team->save();

        return response()->json([
            'result'        => $result,
            'goals_for'     => $goalsFor,
            'goals_against' => $goalsAgainst,
            'match'         => $simResult['match'],
            'summary'       => $summary,
            'opponent'      => ['name' => $opponentName],
            'team'          => [
                'wins'           => $team->wins,
                'draws'          => $team->draws,
                'losses'         => $team->losses,
                'matches_played' => $team->matches_played,
            ],
        ]);
    }
}
