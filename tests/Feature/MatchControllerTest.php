<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Team;
use App\Models\GameMatch;
use App\Services\MatchSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MatchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSimulation(goalsA: 2, goalsB: 1);
    }

    public function test_play_returns_win_and_updates_counters(): void
    {
        $this->mockSimulation(goalsA: 2, goalsB: 1);
        [$team, $opponent] = $this->createTwoTeams();

        $response = $this->postJson('/api/match/play', ['team_id' => $team->id]);

        $response->assertOk()
                 ->assertJsonPath('result', 'win')
                 ->assertJsonPath('goals_for', 2)
                 ->assertJsonPath('goals_against', 1)
                 ->assertJsonPath('team.wins', 1)
                 ->assertJsonPath('team.losses', 0)
                 ->assertJsonPath('team.matches_played', 1);

        $this->assertDatabaseHas('game_matches', ['team_id' => $team->id, 'result' => 'win']);
    }

    public function test_play_records_loss(): void
    {
        $this->mockSimulation(goalsA: 0, goalsB: 3);
        [$team, $opponent] = $this->createTwoTeams();

        $response = $this->postJson('/api/match/play', ['team_id' => $team->id]);

        $response->assertOk()->assertJsonPath('result', 'loss');
        $this->assertDatabaseHas('game_matches', ['result' => 'loss']);
    }

    public function test_play_records_draw(): void
    {
        $this->mockSimulation(goalsA: 1, goalsB: 1);
        [$team, $opponent] = $this->createTwoTeams();

        $response = $this->postJson('/api/match/play', ['team_id' => $team->id]);

        $response->assertOk()->assertJsonPath('result', 'draw');
        $this->assertDatabaseHas('game_matches', ['result' => 'draw']);
    }

    public function test_play_returns_404_when_no_opponent_found(): void
    {
        $team = $this->createTeamWithPlayers();

        $this->postJson('/api/match/play', ['team_id' => $team->id])
             ->assertStatus(404);
    }

    public function test_play_accepts_team_with_many_losses(): void
    {
        $this->mockSimulation(goalsA: 1, goalsB: 0);
        $team     = $this->createTeamWithPlayers(['losses' => 10]);
        $opponent = $this->createTeamWithPlayers(['losses' => 10]);

        $this->postJson('/api/match/play', ['team_id' => $team->id])
             ->assertOk()
             ->assertJsonPath('team.losses', 10)
             ->assertJsonPath('team.wins', 1);
    }

    public function test_play_only_updates_initiating_team_counters(): void
    {
        $this->mockSimulation(goalsA: 1, goalsB: 0);
        [$team, $opponent] = $this->createTwoTeams();

        $this->postJson('/api/match/play', ['team_id' => $team->id]);

        $this->assertEquals(0, $opponent->fresh()->wins);
        $this->assertEquals(0, $opponent->fresh()->matches_played);
    }

    public function test_history_returns_match_list(): void
    {
        $team = $this->createTeamWithPlayers();
        GameMatch::create([
            'team_id'           => $team->id,
            'opponent_snapshot' => ['name' => 'Rival', 'players' => []],
            'goals_for'         => 2,
            'goals_against'     => 0,
            'result'            => 'win',
        ]);

        $this->getJson("/api/match/history/{$team->id}")->assertOk()->assertJsonCount(1);
    }

    // --- helpers ---

    /** @return Team[] */
    private function createTwoTeams(): array
    {
        return [$this->createTeamWithPlayers(), $this->createTeamWithPlayers()];
    }

    private function createTeamWithPlayers(array $teamAttributes = []): Team
    {
        $defaultConfig = array_map(fn($i) => [
            'name'               => "Player {$i}",
            'default_zone_x'     => 50,
            'default_zone_y'     => 25,
            'max_speed'          => 0.5, 'accuracy' => 0.5, 'control' => 0.5,
            'reaction'           => 0.5, 'dribble'  => 0.5, 'strength' => 0.5,
            'endurance'          => 0.5, 'scan_with_ball' => null, 'scan_without_ball' => null,
            'rules_with_ball'    => [],
            'rules_without_ball' => [],
        ], range(0, 2));

        return Team::factory()->create(array_merge(
            ['configuration' => $defaultConfig],
            $teamAttributes,
        ));
    }

    private function mockSimulation(int $goalsA, int $goalsB): void
    {
        $summary = [
            'GoalsA'      => $goalsA,
            'GoalsB'      => $goalsB,
            'totalTime'   => 5000,
            'possessionA' => 2500,
            'possessionB' => 2500,
            'TeamA'       => [],
            'TeamB'       => [],
        ];

        $mock = $this->createMock(MatchSimulation::class);
        $mock->method('loadTeams')->willReturn(null);
        $mock->method('update')->willReturn(null);
        $mock->method('getSummary')->willReturn($summary);

        $this->app->bind(MatchSimulation::class, fn() => $mock);
    }
}
