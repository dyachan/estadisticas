<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\GameTeamPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameFormationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeGamePlayer(string $name = 'Player'): GamePlayer
    {
        return GamePlayer::create(array_merge(
            ['name' => $name],
            array_fill_keys(GamePlayer::UPGRADEABLE_ATTRIBUTES, 0.1)
        ));
    }

    private function validPayload(int $userId, array $playerIds, array $overrides = []): array
    {
        return array_merge([
            'user_id'    => $userId,
            'name'       => 'Team Alpha',
            'player_ids' => $playerIds,
        ], $overrides);
    }

    private function makePlayers(int $count = 3): array
    {
        return array_map(fn($i) => $this->makeGamePlayer("P{$i}"), range(0, $count - 1));
    }

    // ── upsert ────────────────────────────────────────────────────────────────

    public function test_upsert_creates_team_and_assigns_players(): void
    {
        $user    = User::factory()->create();
        $players = $this->makePlayers();
        $ids     = array_map(fn($p) => $p->id, $players);

        $response = $this->postJson('/api/game/formation', $this->validPayload($user->id, $ids));

        $response->assertOk();
        $this->assertDatabaseCount('game_teams', 1);
        $this->assertDatabaseCount('game_team_players', 3);
    }

    public function test_upsert_returns_team_with_active_players(): void
    {
        $user    = User::factory()->create();
        $players = $this->makePlayers();
        $ids     = array_map(fn($p) => $p->id, $players);

        $response = $this->postJson('/api/game/formation', $this->validPayload($user->id, $ids));

        $response->assertOk()
                 ->assertJsonCount(3, 'active_players');
    }

    public function test_upsert_reuses_existing_team_with_same_user_and_name(): void
    {
        $user    = User::factory()->create();
        $players = $this->makePlayers(3);
        $ids     = array_map(fn($p) => $p->id, $players);
        $payload = $this->validPayload($user->id, $ids);

        $this->postJson('/api/game/formation', $payload);
        $this->postJson('/api/game/formation', $payload);

        $this->assertDatabaseCount('game_teams', 1);
    }

    public function test_upsert_closes_previous_active_memberships(): void
    {
        $user     = User::factory()->create();
        $first    = $this->makePlayers(3);
        $second   = $this->makePlayers(3);
        $firstIds = array_map(fn($p) => $p->id, $first);
        $secIds   = array_map(fn($p) => $p->id, $second);
        $payload  = ['user_id' => $user->id, 'name' => 'Team Alpha'];

        $this->postJson('/api/game/formation', array_merge($payload, ['player_ids' => $firstIds]));
        $this->postJson('/api/game/formation', array_merge($payload, ['player_ids' => $secIds]));

        $team = GameTeam::where('user_id', $user->id)->first();
        $closedCount = GameTeamPlayer::where('game_team_id', $team->id)
            ->whereNotNull('left_at')
            ->count();

        $this->assertEquals(3, $closedCount);
    }

    public function test_upsert_new_lineup_is_active_after_swap(): void
    {
        $user     = User::factory()->create();
        $first    = $this->makePlayers(3);
        $second   = $this->makePlayers(3);
        $firstIds = array_map(fn($p) => $p->id, $first);
        $secIds   = array_map(fn($p) => $p->id, $second);
        $payload  = ['user_id' => $user->id, 'name' => 'Team Alpha'];

        $this->postJson('/api/game/formation', array_merge($payload, ['player_ids' => $firstIds]));
        $this->postJson('/api/game/formation', array_merge($payload, ['player_ids' => $secIds]));

        $team        = GameTeam::where('user_id', $user->id)->first();
        $activeCount = GameTeamPlayer::where('game_team_id', $team->id)
            ->whereNull('left_at')
            ->count();

        $this->assertEquals(3, $activeCount);
    }

    public function test_upsert_assigns_correct_position_indices(): void
    {
        $user    = User::factory()->create();
        $players = $this->makePlayers(3);
        $ids     = array_map(fn($p) => $p->id, $players);

        $this->postJson('/api/game/formation', $this->validPayload($user->id, $ids));

        $team = GameTeam::where('user_id', $user->id)->first();
        foreach ([0, 1, 2] as $pos) {
            $this->assertDatabaseHas('game_team_players', [
                'game_team_id'   => $team->id,
                'game_player_id' => $players[$pos]->id,
                'position_index' => $pos,
            ]);
        }
    }

    public function test_upsert_requires_user_id(): void
    {
        $players = $this->makePlayers(3);
        $ids     = array_map(fn($p) => $p->id, $players);

        $this->postJson('/api/game/formation', ['name' => 'X', 'player_ids' => $ids])
             ->assertStatus(422)
             ->assertJsonValidationErrors('user_id');
    }

    public function test_upsert_rejects_wrong_player_count(): void
    {
        $user    = User::factory()->create();
        $players = $this->makePlayers(2);
        $ids     = array_map(fn($p) => $p->id, $players);

        $this->postJson('/api/game/formation', $this->validPayload($user->id, $ids))
             ->assertStatus(422)
             ->assertJsonValidationErrors('player_ids');
    }

    public function test_upsert_rejects_nonexistent_player(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/game/formation', $this->validPayload($user->id, [9991, 9992, 9993]))
             ->assertStatus(422);
    }
}
