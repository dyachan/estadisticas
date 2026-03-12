<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GameTeamPlayer;
use App\Models\Strategy;
use App\Models\StoryMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoguelikeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The simulation replay can be large (5000 ticks); give tests enough headroom.
        ini_set('memory_limit', '512M');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeGamePlayer(string $name = 'P', float $stat = 0.3): GamePlayer
    {
        return GamePlayer::create(array_merge(
            ['name' => $name],
            array_fill_keys(GamePlayer::UPGRADEABLE_ATTRIBUTES, $stat)
        ));
    }

    /**
     * Build a player entry for the /roguelike/play request body.
     */
    private function playPlayerEntry(GamePlayer $player, array $overrides = []): array
    {
        return array_merge([
            'game_player_id'     => $player->id,
            'default_zone_x'     => 50.0,
            'default_zone_y'     => 50.0,
            'rules_with_ball'    => [],
            'rules_without_ball' => [],
        ], $overrides);
    }

    /**
     * Build the /roguelike/play request payload for a team with 3 players.
     */
    private function playPayload(GameTeam $team, array $players, array $overrides = []): array
    {
        return array_merge([
            'game_team_id' => $team->id,
            'players'      => array_map(fn($p) => $this->playPlayerEntry($p), $players),
        ], $overrides);
    }

    /**
     * Build a denormalized formation entry (used when seeding opponent Strategies).
     */
    private function formationEntry(GamePlayer $p): array
    {
        return [
            'game_player_id'    => $p->id,
            'name'              => $p->name,
            'default_zone_x'    => 50.0,
            'default_zone_y'    => 50.0,
            'rules_with_ball'   => [],
            'rules_without_ball'=> [],
            'max_speed'         => $p->max_speed,
            'accuracy'          => $p->accuracy,
            'control'           => $p->control,
            'reaction'          => $p->reaction,
            'dribble'           => $p->dribble,
            'strength'          => $p->strength,
            'endurance'         => $p->endurance,
            'scan_with_ball'    => $p->scan_with_ball,
            'scan_without_ball' => $p->scan_without_ball,
        ];
    }

    /**
     * Seed an opponent GameTeam with a Strategy snapshot at a given record level.
     */
    private function seedOpponent(array $record = []): GameTeam
    {
        $team    = GameTeam::create(array_merge(['name' => 'Rival_' . uniqid()], $record));
        $players = array_map(fn($i) => $this->makeGamePlayer("R{$i}"), [0, 1, 2]);
        Strategy::create([
            'game_team_id'   => $team->id,
            'formation'      => array_map(fn($p) => $this->formationEntry($p), $players),
            'wins'           => $record['wins']           ?? 0,
            'draws'          => $record['draws']          ?? 0,
            'losses'         => $record['losses']         ?? 0,
            'matches_played' => $record['matches_played'] ?? 0,
        ]);
        return $team;
    }

    // ── start ─────────────────────────────────────────────────────────────────

    public function test_start_returns_201_with_team_id_and_players(): void
    {
        $response = $this->postJson('/api/roguelike/start', []);

        $response->assertStatus(201)
                 ->assertJsonStructure(['game_team_id', 'players'])
                 ->assertJsonCount(3, 'players');
    }

    public function test_start_creates_team_and_three_players_in_db(): void
    {
        $this->postJson('/api/roguelike/start', []);

        $this->assertDatabaseCount('game_teams', 1);
        $this->assertDatabaseCount('game_players', 3);
        $this->assertDatabaseCount('game_team_players', 3);
    }

    public function test_start_initializes_all_player_attributes_to_0_1(): void
    {
        $this->postJson('/api/roguelike/start', []);

        GamePlayer::all()->each(function (GamePlayer $player) {
            foreach (GamePlayer::UPGRADEABLE_ATTRIBUTES as $attr) {
                $this->assertEquals(0.1, $player->$attr, "Expected {$attr} = 0.1 on new player");
            }
        });
    }

    public function test_start_records_initial_snapshot_for_each_player(): void
    {
        $this->postJson('/api/roguelike/start', []);

        $this->assertDatabaseCount('player_snapshots', 3);
    }

    public function test_start_uses_provided_player_names(): void
    {
        $response = $this->postJson('/api/roguelike/start', [
            'player_names' => ['Ana', 'Bruno', 'Carlos'],
        ]);

        $names = collect($response->json('players'))->pluck('name')->all();
        $this->assertEquals(['Ana', 'Bruno', 'Carlos'], $names);
    }

    public function test_start_uses_default_names_when_not_provided(): void
    {
        $response = $this->postJson('/api/roguelike/start', []);

        $names = collect($response->json('players'))->pluck('name')->all();
        $this->assertEquals(['Player 0', 'Player 1', 'Player 2'], $names);
    }

    public function test_start_assigns_team_memberships_with_correct_positions(): void
    {
        $response = $this->postJson('/api/roguelike/start', []);

        $teamId = $response->json('game_team_id');
        foreach ([0, 1, 2] as $pos) {
            $this->assertDatabaseHas('game_team_players', [
                'game_team_id'   => $teamId,
                'position_index' => $pos,
                'left_at'        => null,
            ]);
        }
    }

    public function test_start_validates_player_names_must_be_array_of_3(): void
    {
        $this->postJson('/api/roguelike/start', ['player_names' => ['OnlyOne']])
             ->assertStatus(422)
             ->assertJsonValidationErrors('player_names');
    }

    // ── play: no opponent ─────────────────────────────────────────────────────

    public function test_play_returns_no_opponent_at_level_when_no_other_teams(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertOk()
                 ->assertJsonPath('no_opponent_at_level', true);
    }

    public function test_play_no_opponent_response_includes_team_counters(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertJsonStructure(['team' => ['wins', 'draws', 'losses', 'matches_played']]);
    }

    // ── play: updates + strategy ──────────────────────────────────────────────

    public function test_play_updates_player_stats_when_provided(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $payload = $this->playPayload($team, $players);
        $payload['players'][0]['accuracy'] = 0.6;

        $this->postJson('/api/roguelike/play', $payload);

        $this->assertEquals(0.6, GamePlayer::find($players[0]->id)->accuracy);
    }

    public function test_play_records_snapshot_for_each_updated_player(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        // start() already recorded 3 snapshots; updating stats should add more
        $snapshotsBefore = \App\Models\PlayerSnapshot::count();

        $payload = $this->playPayload($team, $players);
        $payload['players'][0]['accuracy'] = 0.7; // trigger update for player 0

        $this->postJson('/api/roguelike/play', $payload);

        $this->assertGreaterThan($snapshotsBefore, \App\Models\PlayerSnapshot::count());
    }

    public function test_play_creates_strategy_with_denormalized_formation(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $strategy = Strategy::where('game_team_id', $teamId)->first();
        $this->assertNotNull($strategy);
        $this->assertCount(3, $strategy->formation);
    }

    public function test_play_strategy_formation_contains_required_fields(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $entry = Strategy::where('game_team_id', $teamId)->first()->formation[0];
        foreach (['name', 'default_zone_x', 'default_zone_y', 'max_speed', 'accuracy'] as $key) {
            $this->assertArrayHasKey($key, $entry, "Formation entry missing key: {$key}");
        }
    }

    public function test_play_strategy_stores_current_team_record_counters(): void
    {
        // Seed a team that already has a record
        $team = GameTeam::create(['name' => 'MyTeam', 'wins' => 2, 'draws' => 1, 'losses' => 0, 'matches_played' => 3]);
        $players = array_map(fn($i) => $this->makeGamePlayer("MP{$i}"), [0, 1, 2]);
        foreach ($players as $i => $p) {
            GameTeamPlayer::create([
                'game_team_id'   => $team->id,
                'game_player_id' => $p->id,
                'position_index' => $i,
                'joined_at'      => now(),
            ]);
        }

        $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $strategy = Strategy::where('game_team_id', $team->id)->first();
        $this->assertEquals(2, $strategy->wins);
        $this->assertEquals(1, $strategy->draws);
        $this->assertEquals(3, $strategy->matches_played);
    }

    // ── play: matchmaking ────────────────────────────────────────────────────

    public function test_play_finds_opponent_with_same_matches_played(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::whereNotIn('id', [])->get()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertOk()
                 ->assertJsonPath('no_opponent_at_level', false);
    }

    public function test_play_matchmaking_falls_back_to_same_matches_played_when_no_same_wins(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId); // wins=0, draws=0, losses=0, matches_played=0

        // Opponent has same matches_played but different wins → should still be picked
        $this->seedOpponent(['matches_played' => 0, 'wins' => 3, 'draws' => 0, 'losses' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertOk()
                 ->assertJsonPath('no_opponent_at_level', false);
    }

    public function test_play_returns_no_opponent_when_only_other_team_has_different_matches_played(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId); // matches_played=0

        $this->seedOpponent(['matches_played' => 5]); // different level

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertOk()
                 ->assertJsonPath('no_opponent_at_level', true);
    }

    // ── play: simulation & persistence ───────────────────────────────────────

    public function test_play_runs_simulation_and_returns_result(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertOk()
                 ->assertJsonStructure(['result', 'goals_for', 'goals_against', 'summary', 'match']);

        $this->assertContains($response->json('result'), ['win', 'loss', 'draw']);
    }

    public function test_play_persists_story_match(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0]);

        $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $this->assertDatabaseCount('story_matches', 1);
        $match = StoryMatch::first();
        $this->assertEquals($teamId, $match->home_team_id);
    }

    public function test_play_increments_matches_played_counter(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0]);

        $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $this->assertEquals(1, $team->fresh()->matches_played);
    }

    public function test_play_increments_exactly_one_outcome_counter(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));
        $result   = $response->json('result');
        $fresh    = $team->fresh();

        $this->assertEquals(1, $fresh->wins + $fresh->draws + $fresh->losses);

        $expected = match($result) {
            'win'  => ['wins' => 1, 'draws' => 0, 'losses' => 0],
            'draw' => ['wins' => 0, 'draws' => 1, 'losses' => 0],
            'loss' => ['wins' => 0, 'draws' => 0, 'losses' => 1],
        };

        $this->assertEquals($expected['wins'],   $fresh->wins);
        $this->assertEquals($expected['draws'],  $fresh->draws);
        $this->assertEquals($expected['losses'], $fresh->losses);
    }

    public function test_play_response_includes_opponent_info(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $this->seedOpponent(['matches_played' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertJsonStructure([
            'opponent' => ['name', 'players', 'wins', 'draws', 'losses', 'matches_played'],
        ]);
    }

    // ── play: pioneer flag ────────────────────────────────────────────────────

    public function test_play_is_pioneer_true_when_no_other_team_at_new_level(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId); // will reach matches_played=1 after play

        // Opponent is at level 0; after the match our team reaches level 1 (no one else there)
        $this->seedOpponent(['matches_played' => 0]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertJsonPath('is_pioneer', true);
    }

    public function test_play_is_pioneer_false_when_another_team_at_same_new_level(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        // Opponent at level 0 (used for matching)
        $this->seedOpponent(['matches_played' => 0]);
        // Another team already at level 1 (our team will reach after play)
        $this->seedOpponent(['matches_played' => 1]);

        $response = $this->postJson('/api/roguelike/play', $this->playPayload($team, $players));

        $response->assertJsonPath('is_pioneer', false);
    }

    // ── play: validation ──────────────────────────────────────────────────────

    public function test_play_requires_game_team_id(): void
    {
        $this->postJson('/api/roguelike/play', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors('game_team_id');
    }

    public function test_play_requires_exactly_3_players(): void
    {
        $team    = GameTeam::create(['name' => 'T']);
        $players = array_map(fn($i) => $this->makeGamePlayer("X{$i}"), [0, 1]);

        $this->postJson('/api/roguelike/play', [
            'game_team_id' => $team->id,
            'players'      => array_map(fn($p) => $this->playPlayerEntry($p), $players),
        ])->assertStatus(422)
          ->assertJsonValidationErrors('players');
    }

    public function test_play_rejects_zone_out_of_range(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $payload = $this->playPayload($team, $players);
        $payload['players'][0]['default_zone_x'] = 150; // > 100

        $this->postJson('/api/roguelike/play', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors('players.0.default_zone_x');
    }

    public function test_play_rejects_stat_out_of_range(): void
    {
        $startResp = $this->postJson('/api/roguelike/start', []);
        $teamId    = $startResp->json('game_team_id');
        $players   = GamePlayer::all()->all();
        $team      = GameTeam::find($teamId);

        $payload = $this->playPayload($team, $players);
        $payload['players'][0]['accuracy'] = 2.5; // > 1

        $this->postJson('/api/roguelike/play', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors('players.0.accuracy');
    }
}
