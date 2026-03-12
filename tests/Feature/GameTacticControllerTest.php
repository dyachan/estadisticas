<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tactic;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameTacticControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'user_id' => User::factory()->create()->id,
            'name'    => 'High Press',
        ], $overrides);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_tactic_and_returns_201(): void
    {
        $response = $this->postJson('/api/game/tactics', $this->validPayload());

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'High Press');

        $this->assertDatabaseCount('tactics', 1);
    }

    public function test_store_persists_optional_zone(): void
    {
        $this->postJson('/api/game/tactics', $this->validPayload([
            'default_zone_x' => 30.0,
            'default_zone_y' => 70.0,
        ]));

        $tactic = Tactic::first();
        $this->assertEquals(30.0, $tactic->default_zone_x);
        $this->assertEquals(70.0, $tactic->default_zone_y);
    }

    public function test_store_persists_with_ball_rules(): void
    {
        $rules = [
            ['condition' => 1, 'action' => 2],
            ['condition' => 3, 'action' => 4],
        ];

        $this->postJson('/api/game/tactics', $this->validPayload(['with_ball' => $rules]));

        $tactic = Tactic::first();
        $this->assertCount(2, $tactic->rules_with_ball);
        $this->assertEquals(1, $tactic->rules_with_ball[0]['condition']);
        $this->assertEquals(2, $tactic->rules_with_ball[0]['action']);
    }

    public function test_store_persists_without_ball_rules(): void
    {
        $rules = [['condition' => 5, 'action' => 6]];

        $this->postJson('/api/game/tactics', $this->validPayload(['without_ball' => $rules]));

        $tactic = Tactic::first();
        $this->assertCount(1, $tactic->rules_without_ball);
        $this->assertEquals(5, $tactic->rules_without_ball[0]['condition']);
    }

    public function test_store_accepts_empty_rules(): void
    {
        $this->postJson('/api/game/tactics', $this->validPayload([
            'with_ball'    => [],
            'without_ball' => [],
        ]))->assertStatus(201);

        $tactic = Tactic::first();
        $this->assertEquals([], $tactic->rules_with_ball);
        $this->assertEquals([], $tactic->rules_without_ball);
    }

    public function test_store_requires_user_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['user_id']);

        $this->postJson('/api/game/tactics', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors('user_id');
    }

    public function test_store_requires_name(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $this->postJson('/api/game/tactics', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_nonexistent_user(): void
    {
        $this->postJson('/api/game/tactics', $this->validPayload(['user_id' => 9999]))
             ->assertStatus(422)
             ->assertJsonValidationErrors('user_id');
    }

    public function test_store_rejects_rule_missing_action(): void
    {
        $this->postJson('/api/game/tactics', $this->validPayload([
            'with_ball' => [['condition' => 1]], // missing action
        ]))->assertStatus(422);
    }

    public function test_store_rejects_zone_out_of_range(): void
    {
        $this->postJson('/api/game/tactics', $this->validPayload(['default_zone_x' => 150]))
             ->assertStatus(422)
             ->assertJsonValidationErrors('default_zone_x');
    }
}
