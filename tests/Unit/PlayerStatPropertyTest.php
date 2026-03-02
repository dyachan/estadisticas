<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\MatchSimulation;
use App\Services\Player;
use App\Services\PlayerFormulas;

/**
 * Statistical property tests for player attributes.
 *
 * Philosophy:
 *  - All assertions are directional/proportional, not exact. This makes them
 *    robust against future retuning of constants without breaking the tests.
 *  - Extreme-value tests (e.g. dribble=1.0) are included inside the same
 *    statistical suite rather than as deterministic tests, because we cannot
 *    guarantee the code will remain deterministic in the future.
 *  - N is chosen large enough that the variance of the estimated proportion is
 *    negligible compared to the expected difference between the two scenarios.
 */
class PlayerStatPropertyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Minimal Player config. Any key in $overrides replaces the default.
     */
    private function makePlayerConfig(array $overrides = []): array
    {
        return array_merge([
            'team'             => 'Team A',
            'name'             => 'Tester',
            'rules'            => [[], []],
            'x'                => 200.0,
            'y'                => 300.0,
            'baseX'            => 200.0,
            'baseY'            => 300.0,
            'defaultAction'    => 'Stay in my zone',
            'currentFieldSide' => 'bottom',
            'fieldWidth'       => 400.0,
            'fieldHeight'      => 600.0,
            'assistDistance'   => 76.0,  // PLAYER_SIZE(38) * BODYASSIST_FACTOR(2)
        ], $overrides);
    }

    /**
     * Build a MatchSimulation with 6 dummy players (3 per team) and then apply
     * custom attributes to the "focus" players at indices 0 (attacker, Team A)
     * and 3 (defender/opponent, Team B). All other players are pushed far from
     * the ball so they do not interfere.
     *
     * The attacker starts with hasBall=true at the field center.
     * The defender is placed ~20 px away (well inside PLAYER_SIZE=38).
     */
    private function setupChallengeMatch(
        float $attackerDribble,
        float $defenderReaction
    ): MatchSimulation {
        $match = new MatchSimulation(400, 600);
        $match->loadTeams(
            $this->minimalTeamData([['dribble' => $attackerDribble]]),
            $this->minimalTeamData([['reaction' => $defenderReaction]])
        );

        $attacker = $match->players[0]; // Team A
        $attacker->x        = 200.0;
        $attacker->y        = 300.0;
        $attacker->hasBall  = true;
        $attacker->ballCooldown = 0;
        $attacker->bodyCooldown = 0;
        $attacker->target   = null;

        $match->ball->x  = 200.0;
        $match->ball->y  = 300.0;
        $match->ball->vx = 0.0;
        $match->ball->vy = 0.0;

        $defender = $match->players[3]; // Team B
        $defender->x        = 220.0; // 20 px away → inside CONTROL_DISTANCE(38)
        $defender->y        = 300.0;
        $defender->ballCooldown = 0;
        $defender->bodyCooldown = 0;
        $defender->target   = null;

        // Push other players far from the ball so they do not interfere
        foreach ([1, 2] as $i) {
            $match->players[$i]->x = 50.0;
            $match->players[$i]->y = 50.0 + $i * 40;
            $match->players[$i]->target = null;
        }
        foreach ([4, 5] as $i) {
            $match->players[$i]->x = 350.0;
            $match->players[$i]->y = 500.0 + ($i - 4) * 40;
            $match->players[$i]->target = null;
        }

        return $match;
    }

    /**
     * Minimal team data understood by MatchSimulation::loadTeams().
     * $playerOverrides[0] applies extra keys to the first player only.
     */
    private function minimalTeamData(array $playerOverrides = []): array
    {
        $default = [
            'name'             => 'P',
            'rules'            => [[], []],
            'defaultZone'      => ['x' => 50, 'y' => 25],
            'scanWithBall'     => null,
            'scanWithoutBall'  => null,
            'maxSpeed'         => 0.5,
            'accuracy'         => 0.5,
            'control'          => 0.5,
            'reaction'         => 0.5,
            'dribble'          => 0.5,
            'strength'         => 0.5,
            'endurance'        => 0.5,
        ];

        return [
            'players' => [
                array_merge($default, ['name' => 'P1'], $playerOverrides[0] ?? []),
                array_merge($default, ['name' => 'P2']),
                array_merge($default, ['name' => 'P3']),
            ],
        ];
    }

    /**
     * Run N challenge trials and return total steals by the defender (players[3]).
     */
    private function runChallengeTrials(
        float $attackerDribble,
        float $defenderReaction,
        int $n
    ): int {
        $steals = 0;
        for ($i = 0; $i < $n; $i++) {
            $match = $this->setupChallengeMatch($attackerDribble, $defenderReaction);
            $match->update();
            $steals += $match->players[3]->summary->stealedBalls;
        }
        return $steals;
    }

    // -------------------------------------------------------------------------
    // 1. ACCURACY – formula direction (unit, fast)
    // -------------------------------------------------------------------------

    public function test_accuracy_formula_direction(): void
    {
        // Higher accuracy value → smaller deviation (more precise)
        $this->assertGreaterThan(
            PlayerFormulas::accuracyDeviation(0.9),
            PlayerFormulas::accuracyDeviation(0.1),
            'Low accuracy (0.1) should produce larger deviation than high accuracy (0.9)'
        );

        // Boundaries
        $this->assertEquals(
            PlayerFormulas::ACCURACY_DEVIATION_MAX,
            PlayerFormulas::accuracyDeviation(0.0),
            'value=0 should return MAX deviation'
        );
        $this->assertEquals(
            PlayerFormulas::ACCURACY_DEVIATION_MIN,
            PlayerFormulas::accuracyDeviation(1.0),
            'value=1 should return MIN deviation'
        );

        // Monotonic: more accuracy always means less deviation
        $prev = PHP_FLOAT_MAX;
        foreach ([0.0, 0.2, 0.4, 0.6, 0.8, 1.0] as $v) {
            $dev = PlayerFormulas::accuracyDeviation($v);
            $this->assertLessThanOrEqual($prev, $dev,
                "accuracyDeviation should decrease (or stay equal) as value increases");
            $prev = $dev;
        }
    }

    // -------------------------------------------------------------------------
    // 2. DRIBBLE – higher dribble → fewer steals suffered (N=300 trials)
    // -------------------------------------------------------------------------

    public function test_higher_dribble_reduces_steals_suffered(): void
    {
        $n = 300;

        // Attacker dribble=0.9 → stealThreshold ≈ max(0, 0.5 − 0.45) = 0.05  → ~5% steals
        $highDribbleSteals = $this->runChallengeTrials(0.9, 0.5, $n);

        // Attacker dribble=0.1 → stealThreshold ≈ max(0, 0.5 − 0.05) = 0.45  → ~45% steals
        $lowDribbleSteals  = $this->runChallengeTrials(0.1, 0.5, $n);

        $this->assertGreaterThan(
            $highDribbleSteals * 3,
            $lowDribbleSteals,
            "Low-dribble attacker ($lowDribbleSteals steals) should suffer at least 3× " .
            "more steals than high-dribble attacker ($highDribbleSteals steals) over $n trials"
        );
    }

    // -------------------------------------------------------------------------
    // 3. DRIBBLE extreme – dribble=1.0, reaction=0.0 → near-zero steal rate
    // -------------------------------------------------------------------------

    public function test_max_dribble_vs_min_reaction_produces_very_few_steals(): void
    {
        $n = 200;

        // stealThreshold = max(0, 0 − 0.5) = 0   takeoffThreshold = 1 − max(0,1−0) = 0
        // Mathematically: 0% steal and 0% takeoff → always dribble
        $steals = $this->runChallengeTrials(1.0, 0.0, $n);

        // Allow up to 5% noise margin (formula or code might change in future)
        $this->assertLessThan(
            (int)($n * 0.05) + 1,
            $steals,
            "Max dribble vs zero reaction should almost never result in a steal ($steals/$n)"
        );
    }

    // -------------------------------------------------------------------------
    // 4. REACTION – higher reaction → more steals (N=300 trials)
    // -------------------------------------------------------------------------

    public function test_higher_reaction_increases_steal_rate(): void
    {
        $n = 300;

        // Defender reaction=0.9, dribble=0.5 → stealThreshold ≈ max(0,0.9−0.25) = 0.65 → ~65% steals
        $highReactionSteals = $this->runChallengeTrials(0.5, 0.9, $n);

        // Defender reaction=0.1, dribble=0.5 → stealThreshold ≈ max(0,0.1−0.25) = 0   → ~0% steals
        $lowReactionSteals  = $this->runChallengeTrials(0.5, 0.1, $n);

        $this->assertGreaterThan(
            $lowReactionSteals * 3,
            $highReactionSteals,
            "High-reaction defender ($highReactionSteals steals) should steal at least 3× " .
            "more than low-reaction defender ($lowReactionSteals steals) over $n trials"
        );
    }

    // -------------------------------------------------------------------------
    // 5. MAX SPEED – higher speed → more distance in same ticks
    // -------------------------------------------------------------------------

    public function test_higher_maxspeed_increases_distance_traveled(): void
    {
        $ticks = 200;
        $target = ['x' => 9999.0, 'y' => 9999.0]; // unreachable → player runs the whole time

        $fast = new Player($this->makePlayerConfig(['maxSpeed' => 0.9, 'name' => 'Fast']));
        $fast->target = $target;

        $slow = new Player($this->makePlayerConfig(['maxSpeed' => 0.1, 'name' => 'Slow']));
        $slow->target = $target;

        for ($t = 0; $t < $ticks; $t++) {
            $fast->update(1.0);
            $slow->update(1.0);
        }

        $this->assertGreaterThan(
            $slow->summary->distanceTraveled * 1.5,
            $fast->summary->distanceTraveled,
            "High-maxSpeed player ({$fast->summary->distanceTraveled}) " .
            "should travel at least 1.5× farther than low-maxSpeed player " .
            "({$slow->summary->distanceTraveled}) over $ticks ticks"
        );
    }

    // -------------------------------------------------------------------------
    // 6. ENDURANCE – higher endurance → faster strength recovery when resting
    // -------------------------------------------------------------------------

    public function test_higher_endurance_recovers_strength_faster(): void
    {
        $ticks = 300;

        $highEnd = new Player($this->makePlayerConfig([
            'endurance' => 0.9,
            'name'      => 'HighEnd',
        ]));
        $highEnd->currentStrength = 0.0;
        $highEnd->target = null; // resting → no depletion, pure recovery

        $lowEnd = new Player($this->makePlayerConfig([
            'endurance' => 0.1,
            'name'      => 'LowEnd',
        ]));
        $lowEnd->currentStrength = 0.0;
        $lowEnd->target = null;

        for ($t = 0; $t < $ticks; $t++) {
            $highEnd->update(1.0);
            $lowEnd->update(1.0);
        }

        $this->assertGreaterThan(
            $lowEnd->currentStrength * 3,
            $highEnd->currentStrength,
            "High-endurance player ({$highEnd->currentStrength} strength) should recover " .
            "at least 3× more strength than low-endurance player ({$lowEnd->currentStrength}) " .
            "after $ticks resting ticks"
        );
    }

    // -------------------------------------------------------------------------
    // 7. ENDURANCE – sustained distance: more endurance → more distance over long run
    // -------------------------------------------------------------------------

    public function test_higher_endurance_sustains_more_distance_over_long_run(): void
    {
        $ticks = 1000;
        $target = ['x' => 9999.0, 'y' => 9999.0];

        // Same strength depletion rate, different endurance
        $highEnd = new Player($this->makePlayerConfig([
            'endurance' => 0.9,
            'strength'  => 0.5,
            'maxSpeed'  => 0.5,
            'name'      => 'HighEnd',
        ]));
        $highEnd->target = $target;

        $lowEnd = new Player($this->makePlayerConfig([
            'endurance' => 0.1,
            'strength'  => 0.5,
            'maxSpeed'  => 0.5,
            'name'      => 'LowEnd',
        ]));
        $lowEnd->target = $target;

        for ($t = 0; $t < $ticks; $t++) {
            $highEnd->update(1.0);
            $lowEnd->update(1.0);
        }

        $this->assertGreaterThan(
            $lowEnd->summary->distanceTraveled,
            $highEnd->summary->distanceTraveled,
            "High-endurance player ({$highEnd->summary->distanceTraveled}) should travel " .
            "farther than low-endurance player ({$lowEnd->summary->distanceTraveled}) " .
            "over $ticks ticks of continuous running"
        );
    }

    // -------------------------------------------------------------------------
    // 8. CONTROL – higher control → can control faster balls (N=100 trials)
    // -------------------------------------------------------------------------

    public function test_higher_control_enables_controlling_faster_balls(): void
    {
        // Ball speed = 6.0
        // Low  control (0.1) → threshold = 3.6 → 6.0 > 3.6  → always fails
        // High control (0.9) → threshold = 8.4 → 6.0 < 8.4  → always succeeds
        $n = 100;

        $highControlSuccesses = 0;
        $lowControlSuccesses  = 0;

        for ($i = 0; $i < $n; $i++) {
            $highControlSuccesses += $this->runControlTrial(0.9, 6.0);
            $lowControlSuccesses  += $this->runControlTrial(0.1, 6.0);
        }

        $this->assertGreaterThan(
            $highControlSuccesses * 0.5, // low should control much less than half of what high does
            $highControlSuccesses,       // (trivially true unless high is 0 — guard against bugs)
        );

        $this->assertGreaterThan(
            $lowControlSuccesses * 5,
            $highControlSuccesses,
            "High-control player ($highControlSuccesses/n) should control the ball " .
            "at least 5× more often than low-control player ($lowControlSuccesses/n)"
        );
    }

    /**
     * Single control trial: position one player near a moving ball (no owner),
     * run one tick, return 1 if the player took possession, 0 otherwise.
     */
    private function runControlTrial(float $controlValue, float $ballSpeed): int
    {
        $match = new MatchSimulation(400, 600);
        $match->loadTeams(
            $this->minimalTeamData([['control' => $controlValue]]),
            $this->minimalTeamData()
        );

        // Ball at center, moving upward at $ballSpeed
        $match->ball->x  = 200.0;
        $match->ball->y  = 300.0;
        $match->ball->vx = 0.0;
        $match->ball->vy = -$ballSpeed;

        // Test player (Team A, index 0) is right next to the ball
        $tester = $match->players[0];
        $tester->x           = 200.0;
        $tester->y           = 300.0;
        $tester->hasBall     = false;
        $tester->ballCooldown = 0;
        $tester->bodyCooldown = 0;
        $tester->target      = null;

        // All other players are far from the ball
        foreach ([1, 2] as $i) {
            $match->players[$i]->x = 50.0;
            $match->players[$i]->y = 50.0 + $i * 40;
        }
        foreach ([3, 4, 5] as $i) {
            $match->players[$i]->x = 350.0;
            $match->players[$i]->y = 500.0 + ($i - 3) * 40;
        }

        $match->update();

        return $match->players[0]->hasBall ? 1 : 0;
    }
}
