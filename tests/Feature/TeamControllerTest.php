<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function playerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Alpha',
            'default_zone_x' => 50,
            'default_zone_y' => 25,
        ], $overrides);
    }

    private function validTeamPayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Los Rockets',
            'configuration' => [
                $this->playerPayload(['name' => 'Alpha']),
                $this->playerPayload(['name' => 'Beta']),
                $this->playerPayload(['name' => 'Gamma']),
            ],
        ], $overrides);
    }

    private function createTeamWithConfig(array $teamAttributes = []): Team
    {
        return Team::factory()->create(array_merge([
            'configuration' => [
                ['name' => 'Player 0', 'default_zone_x' => 50, 'default_zone_y' => 25,
                 'max_speed' => 0.5, 'accuracy' => 0.5, 'control' => 0.5, 'reaction' => 0.5,
                 'dribble' => 0.5, 'strength' => 0.5, 'endurance' => 0.5,
                 'scan_with_ball' => null, 'scan_without_ball' => null,
                 'rules_with_ball' => [], 'rules_without_ball' => []],
                ['name' => 'Player 1', 'default_zone_x' => 30, 'default_zone_y' => 50,
                 'max_speed' => 0.5, 'accuracy' => 0.5, 'control' => 0.5, 'reaction' => 0.5,
                 'dribble' => 0.5, 'strength' => 0.5, 'endurance' => 0.5,
                 'scan_with_ball' => null, 'scan_without_ball' => null,
                 'rules_with_ball' => [], 'rules_without_ball' => []],
                ['name' => 'Player 2', 'default_zone_x' => 70, 'default_zone_y' => 70,
                 'max_speed' => 0.5, 'accuracy' => 0.5, 'control' => 0.5, 'reaction' => 0.5,
                 'dribble' => 0.5, 'strength' => 0.5, 'endurance' => 0.5,
                 'scan_with_ball' => null, 'scan_without_ball' => null,
                 'rules_with_ball' => [], 'rules_without_ball' => []],
            ],
        ], $teamAttributes));
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_team_with_configuration(): void
    {
        $response = $this->postJson('/api/teams', $this->validTeamPayload());

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Los Rockets')
                 ->assertJsonCount(3, 'data.configuration');

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_store_applies_default_attributes(): void
    {
        $this->postJson('/api/teams', $this->validTeamPayload());

        $team = Team::first();
        $this->assertEquals(0.5, $team->configuration[0]['accuracy']);
        $this->assertEquals(0.5, $team->configuration[0]['max_speed']);
    }

    public function test_store_fails_when_name_already_exists(): void
    {
        Team::factory()->create(['name' => 'Los Rockets']);

        $this->postJson('/api/teams', $this->validTeamPayload())
             ->assertStatus(422)
             ->assertJsonValidationErrors('name');
    }

    public function test_store_fails_with_wrong_player_count(): void
    {
        $payload = $this->validTeamPayload();
        $payload['configuration'] = array_slice($payload['configuration'], 0, 2);

        $this->postJson('/api/teams', $payload)->assertStatus(422);
    }

    public function test_store_fails_without_configuration(): void
    {
        $this->postJson('/api/teams', ['name' => 'Empty'])->assertStatus(422);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_show_returns_team_with_configuration(): void
    {
        $team = $this->createTeamWithConfig();

        $this->getJson("/api/teams/{$team->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $team->id)
             ->assertJsonCount(3, 'data.configuration');
    }

    // ── upgrade ───────────────────────────────────────────────────────────────

    public function test_upgrade_increases_player_attribute(): void
    {
        $team = $this->createTeamWithConfig();

        $this->postJson("/api/teams/{$team->id}/upgrade", [
            'position_index' => 0,
            'attribute'      => 'accuracy',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertEquals(0.55, $team->fresh()->configuration[0]['accuracy']);
    }

    public function test_upgrade_caps_at_one(): void
    {
        $config = $this->createTeamWithConfig()->configuration;
        $config[0]['accuracy'] = 0.98;
        $team = $this->createTeamWithConfig(['configuration' => $config, 'name' => 'Team B']);

        $this->postJson("/api/teams/{$team->id}/upgrade", [
            'position_index' => 0,
            'attribute'      => 'accuracy',
        ]);

        $this->assertEquals(1.0, $team->fresh()->configuration[0]['accuracy']);
    }

    public function test_upgrade_rejects_invalid_attribute(): void
    {
        $team = $this->createTeamWithConfig();

        $this->postJson("/api/teams/{$team->id}/upgrade", [
            'position_index' => 0,
            'attribute'      => 'goals',
        ])->assertStatus(422);
    }

    public function test_upgrade_rejects_invalid_position(): void
    {
        $team = $this->createTeamWithConfig();

        $this->postJson("/api/teams/{$team->id}/upgrade", [
            'position_index' => 9,
            'attribute'      => 'accuracy',
        ])->assertStatus(422);
    }

    // ── assignRule ────────────────────────────────────────────────────────────

    public function test_assign_rule_sets_with_ball_slot(): void
    {
        $team = $this->createTeamWithConfig();

        $this->postJson("/api/teams/{$team->id}/rule", [
            'position_index' => 0,
            'slot'           => 'with_ball',
            'priority'       => 0,
            'condition'      => 'I has the ball',
            'action'         => 'Shoot to goal',
        ])->assertOk()->assertJsonPath('success', true);

        $rule = $team->fresh()->configuration[0]['rules_with_ball'][0];
        $this->assertEquals('I has the ball', $rule['condition']);
        $this->assertEquals('Shoot to goal',  $rule['action']);
    }

    public function test_assign_rule_sets_without_ball_slot(): void
    {
        $team = $this->createTeamWithConfig();

        $this->postJson("/api/teams/{$team->id}/rule", [
            'position_index' => 1,
            'slot'           => 'without_ball',
            'priority'       => 0,
            'condition'      => 'The ball is in my side',
            'action'         => 'Go to the ball',
        ])->assertOk();

        $rule = $team->fresh()->configuration[1]['rules_without_ball'][0];
        $this->assertEquals('The ball is in my side', $rule['condition']);
    }

    public function test_assign_rule_replaces_existing_at_same_priority(): void
    {
        $team = $this->createTeamWithConfig();

        $base = ['position_index' => 0, 'slot' => 'with_ball', 'priority' => 0, 'condition' => 'I has the ball'];

        $this->postJson("/api/teams/{$team->id}/rule", array_merge($base, ['action' => 'Pass the ball']));
        $this->postJson("/api/teams/{$team->id}/rule", array_merge($base, ['action' => 'Shoot to goal']));

        $rules = $team->fresh()->configuration[0]['rules_with_ball'];
        $this->assertCount(1, $rules);
        $this->assertEquals('Shoot to goal', $rules[0]['action']);
    }
}
