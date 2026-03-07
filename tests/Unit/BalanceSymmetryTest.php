<?php

namespace Tests\Unit;

/**
 * Symmetry baseline tests.
 *
 * Both teams are identical clones. Over enough matches the simulation
 * should not systematically favor either side. These tests establish
 * a "healthy baseline" before any attribute imbalance is introduced.
 *
 * Thresholds are intentionally loose because random variance in a finite
 * number of matches is expected. The goal is to detect gross structural
 * bias (e.g., Team A always wins due to spawn position), not perfect balance.
 */
class BalanceSymmetryTest extends BalanceTestCase
{
    // -------------------------------------------------------------------------
    // 1. Game events sanity check
    // -------------------------------------------------------------------------

    /**
     * The most basic sanity check: the simulation must actually produce
     * activity. If challenges or ball controls are zero, something is broken
     * at the simulation or rule-set level.
     */
    public function test_simulation_produces_game_events(): void
    {
        $result = $this->runMatch($this->makeTeam(), $this->makeTeam());

        $totalActivity = $result['totalChallenges']
            + $result['teamA']['controledBalls']
            + $result['teamB']['controledBalls'];

        $this->assertGreaterThan(
            0,
            $totalActivity,
            'Simulation should produce challenges and/or ball controls over '
            . self::TICKS_PER_MATCH . ' ticks. Got 0 — check rule set or loadTeams config.'
        );
    }

    /**
     * Identical teams should both score at least once over a set of matches.
     * Zero goals from one side over many matches signals a structural issue.
     */
    public function test_both_teams_can_score(): void
    {
        $totalA  = 0;
        $totalB  = 0;
        $matches = 8;

        for ($i = 0; $i < $matches; $i++) {
            $r      = $this->runMatch($this->makeTeam(), $this->makeTeam());
            $totalA += $r['goalsA'];
            $totalB += $r['goalsB'];
        }

        $this->assertGreaterThan(
            0,
            $totalA + $totalB,
            "No goals in $matches matches — rule set or shooting mechanics may be broken."
        );

        // Each team should score at least once in 8 matches
        $this->assertGreaterThan(
            0,
            $totalA,
            "Team A never scored in $matches matches (Team B: $totalB goals). Structural bias suspected."
        );
        $this->assertGreaterThan(
            0,
            $totalB,
            "Team B never scored in $matches matches (Team A: $totalA goals). Structural bias suspected."
        );
    }

    // -------------------------------------------------------------------------
    // 2. Possession symmetry
    // -------------------------------------------------------------------------

    /**
     * Over a set of matches with identical teams, no team should dominate
     * possession. A team averaging > 65% possession signals that spawn
     * positions, field geometry, or turn-order give one side a systematic edge.
     */
    public function test_identical_teams_have_balanced_possession(): void
    {
        $avg = $this->runMatchSet(fn() => $this->makeTeam(), fn() => $this->makeTeam());

        $this->assertLessThan(
            0.65,
            $avg['possessionA'],
            sprintf(
                'Team A averaged %.1f%% possession with identical teams (threshold: 65%%).',
                $avg['possessionA'] * 100
            )
        );

        $this->assertLessThan(
            0.65,
            $avg['possessionB'],
            sprintf(
                'Team B averaged %.1f%% possession with identical teams (threshold: 65%%).',
                $avg['possessionB'] * 100
            )
        );
    }

    // -------------------------------------------------------------------------
    // 3. Goal symmetry
    // -------------------------------------------------------------------------

    /**
     * Over many matches, neither team should have a drastically higher goal
     * count than the other. A 70/30 split or worse over 10 matches would
     * indicate a structural advantage.
     *
     * NOTE: variance is naturally high for goals (rare discrete events), so
     * we use 10 matches and a generous 72/28 threshold.
     */
    public function test_identical_teams_have_balanced_goal_distribution(): void
    {
        $totalA  = 0;
        $totalB  = 0;
        $matches = 10;

        for ($i = 0; $i < $matches; $i++) {
            $r      = $this->runMatch($this->makeTeam(), $this->makeTeam());
            $totalA += $r['goalsA'];
            $totalB += $r['goalsB'];
        }

        $total = $totalA + $totalB;

        if ($total === 0) {
            $this->markTestSkipped(
                "No goals scored in $matches matches — cannot evaluate goal distribution. "
                . 'Check test_both_teams_can_score first.'
            );
        }

        $dominantShare = max($totalA, $totalB) / $total;

        $this->assertLessThan(
            0.72,
            $dominantShare,
            sprintf(
                'Goal distribution is %d-%d (%.0f%% vs %.0f%%) over %d matches. '
                . 'Expected no team to exceed 72%%.',
                $totalA, $totalB,
                ($totalA / $total) * 100,
                ($totalB / $total) * 100,
                $matches
            )
        );
    }

    // -------------------------------------------------------------------------
    // 4. Challenge symmetry
    // -------------------------------------------------------------------------

    /**
     * With identical teams, challenges must occur and both sides must steal
     * at a roughly similar rate. We do NOT assert the exact steal rate here
     * (that belongs in PlayerStatPropertyTest) — this is an exploration check
     * for structural asymmetry.
     *
     * NOTE: stealRate = stealedBalls / challengedRivalWithBall may read lower
     * than the formula's theoretical 25% because challengedRivalWithBall is
     * incremented once per *opponent in range* per tick (up to 3 at once),
     * while a steal can only occur once per challenge round.
     */
    public function test_challenges_happen_and_are_symmetric(): void
    {
        $avg = $this->runMatchSet(fn() => $this->makeTeam(), fn() => $this->makeTeam());

        // Challenges are rare in this rule set (carrier moves quickly toward goal),
        // but should happen at least once over 5 matches of 2000 ticks.
        // Low challenge counts themselves are useful signal: they indicate that
        // the ACTIVE_RULES make ball possession very brief.
        $this->assertGreaterThan(
            0,
            $avg['totalChallenges'],
            'Zero challenge events over ' . self::TICKS_PER_MATCH . ' ticks. '
            . 'Ball carrier is never contested — check rule set dynamics.'
        );

        // Both teams should steal at a similar rate (within 3× of each other)
        $stealsA = $avg['teamA']['stealedBalls'];
        $stealsB = $avg['teamB']['stealedBalls'];

        if ($stealsA > 0 && $stealsB > 0) {
            $ratio = max($stealsA, $stealsB) / min($stealsA, $stealsB);
            $this->assertLessThan(
                3.0,
                $ratio,
                sprintf(
                    'Steal asymmetry too large: Team A=%.1f vs Team B=%.1f steals/match. '
                    . 'Identical teams should steal at similar rates.',
                    $stealsA, $stealsB
                )
            );
        }
    }

    // -------------------------------------------------------------------------
    // 5. Distance symmetry
    // -------------------------------------------------------------------------

    /**
     * Both teams should cover similar total distance. A large asymmetry would
     * suggest that one team spends significantly more time idle or is
     * structurally prevented from moving (e.g., body cooldown loops).
     */
    public function test_identical_teams_travel_similar_distance(): void
    {
        $avg = $this->runMatchSet(fn() => $this->makeTeam(), fn() => $this->makeTeam());

        $distA = $avg['teamA']['distanceTraveled'];
        $distB = $avg['teamB']['distanceTraveled'];

        $larger  = max($distA, $distB);
        $smaller = min($distA, $distB);

        $this->assertGreaterThan(
            0.0,
            $smaller,
            'One team traveled zero distance — players are not moving at all.'
        );

        // Allow up to a 40% gap (generous due to game-state variance)
        $this->assertLessThan(
            $smaller * 1.40,
            $larger,
            sprintf(
                'Distance asymmetry too large: Team A=%.0f vs Team B=%.0f (%.0f%% gap).',
                $distA, $distB,
                abs($distA - $distB) / min($distA, $distB) * 100
            )
        );
    }
}
