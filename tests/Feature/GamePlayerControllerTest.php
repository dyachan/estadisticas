<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GamePlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GamePlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createPlayer(array $overrides = []): GamePlayer
    {
        return GamePlayer::create(array_merge(
            ['name' => 'Test Player'],
            array_fill_keys(GamePlayer::UPGRADEABLE_ATTRIBUTES, 0.1),
            $overrides
        ));
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_returns_201_with_player_data(): void
    {
        $response = $this->postJson('/api/game/players', ['name' => 'Nico']);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Nico');
    }

    public function test_store_initializes_all_attributes_to_0_1(): void
    {
        $this->postJson('/api/game/players', ['name' => 'Nico']);

        $player = GamePlayer::first();
        foreach (GamePlayer::UPGRADEABLE_ATTRIBUTES as $attr) {
            $this->assertEquals(0.1, $player->$attr, "Expected $attr = 0.1");
        }
    }

    public function test_store_persists_player_in_database(): void
    {
        $this->postJson('/api/game/players', ['name' => 'Nico']);

        $this->assertDatabaseCount('game_players', 1);
        $this->assertDatabaseHas('game_players', ['name' => 'Nico']);
    }

    public function test_store_requires_name(): void
    {
        $this->postJson('/api/game/players', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_missing_name(): void
    {
        $this->postJson('/api/game/players', ['name' => ''])
             ->assertStatus(422)
             ->assertJsonValidationErrors('name');
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_changes_single_attribute(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", ['accuracy' => 0.7])
             ->assertOk()
             ->assertJsonPath('accuracy', 0.7);

        $this->assertEquals(0.7, $player->fresh()->accuracy);
    }

    public function test_update_changes_multiple_attributes(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", [
            'max_speed' => 0.5,
            'dribble'   => 0.8,
        ])->assertOk();

        $fresh = $player->fresh();
        $this->assertEquals(0.5, $fresh->max_speed);
        $this->assertEquals(0.8, $fresh->dribble);
    }

    public function test_update_records_snapshot_after_save(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", ['accuracy' => 0.5]);

        $this->assertDatabaseCount('player_snapshots', 1);
        $snapshot = $player->snapshots()->first();
        $this->assertEquals(0.5, $snapshot->accuracy);
    }

    public function test_update_snapshot_captures_all_current_attributes(): void
    {
        $player = $this->createPlayer(['reaction' => 0.6]);

        $this->putJson("/api/game/players/{$player->id}", ['accuracy' => 0.9]);

        $snapshot = $player->snapshots()->first();
        $this->assertEquals(0.9, $snapshot->accuracy);
        $this->assertEquals(0.6, $snapshot->reaction);
    }

    public function test_update_returns_422_when_no_attributes_provided(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", [])
             ->assertStatus(422);
    }

    public function test_update_rejects_attribute_above_1(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", ['accuracy' => 1.5])
             ->assertStatus(422)
             ->assertJsonValidationErrors('accuracy');
    }

    public function test_update_rejects_attribute_below_0(): void
    {
        $player = $this->createPlayer();

        $this->putJson("/api/game/players/{$player->id}", ['accuracy' => -0.1])
             ->assertStatus(422)
             ->assertJsonValidationErrors('accuracy');
    }

    public function test_update_returns_404_for_nonexistent_player(): void
    {
        $this->putJson('/api/game/players/9999', ['accuracy' => 0.5])
             ->assertStatus(404);
    }
}
