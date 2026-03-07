<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Team;
use App\Services\MatchmakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MatchmakingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchmakingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchmakingService();
    }

    public function test_returns_null_when_no_opponents_exist(): void
    {
        $team = Team::factory()->create();

        $this->assertNull($this->service->findOpponent($team));
    }

    public function test_excludes_own_team(): void
    {
        $team = Team::factory()->create();

        $this->assertNull($this->service->findOpponent($team));
    }

    public function test_includes_teams_with_many_losses(): void
    {
        $team  = Team::factory()->create(['losses' => 0]);
        $rival = Team::factory()->create(['losses' => 10]);

        $this->assertEquals($rival->id, $this->service->findOpponent($team)->id);
    }

    public function test_prioritizes_closest_losses(): void
    {
        $team = Team::factory()->create(['losses' => 2]);
        $near = Team::factory()->create(['losses' => 2]);
        $far  = Team::factory()->create(['losses' => 5]);

        $this->assertEquals($near->id, $this->service->findOpponent($team)->id);
    }

    public function test_breaks_losses_tie_by_matches_played(): void
    {
        $team = Team::factory()->create(['losses' => 1, 'matches_played' => 10]);
        $near = Team::factory()->create(['losses' => 1, 'matches_played' => 10]);
        $far  = Team::factory()->create(['losses' => 1, 'matches_played' => 20]);

        $this->assertEquals($near->id, $this->service->findOpponent($team)->id);
    }

    public function test_breaks_tie_by_wins_after_matches_played(): void
    {
        $team = Team::factory()->create(['losses' => 1, 'matches_played' => 5, 'wins' => 3]);
        $near = Team::factory()->create(['losses' => 1, 'matches_played' => 5, 'wins' => 3]);
        $far  = Team::factory()->create(['losses' => 1, 'matches_played' => 5, 'wins' => 0]);

        $this->assertEquals($near->id, $this->service->findOpponent($team)->id);
    }
}
