<?php

namespace Tests\Unit;

/**
 * Attribute sweep balance tests.
 *
 * Each test pits an "extreme" team (one attribute pushed to max or min) against
 * a neutral team (all stats = 0.5), or two opposite extremes against each other.
 *
 * Two goals per test:
 *   1. DIRECTIONAL  — the attribute has a measurable effect on the game.
 *   2. COLLAPSE     — no single team dominates so thoroughly that the game
 *                     becomes meaningless (possession > COLLAPSE_POSSESSION or
 *                     goal share > COLLAPSE_GOAL_SHARE).
 *                     A failing collapse assertion is a useful balance signal,
 *                     not necessarily a hard bug.
 *
 * NOTE on reaction / dribble:
 *   Challenges are rare (~3/match) with ACTIVE_RULES because ball carriers
 *   reach the shooting threshold before opponents close to 38 px.
 *   To compensate, challenge-based tests use more matches (15) so the total
 *   challenge pool is large enough (~45 events) to show a clear signal.
 *   If you need richer challenge coverage, consider adding CONTESTED_RULES
 *   (Option A) to BalanceTestCase — a rule set where carriers hold the ball
 *   in contested space instead of rushing toward goal.
 */
class BalanceAttributeSweepTest extends BalanceTestCase
{
    private const COLLAPSE_POSSESSION = 0.82;
    private const COLLAPSE_GOAL_SHARE = 0.82;

    /**
     * Rule set used exclusively by the scanWithBall test.
     * Carrier tries to pass first; this forces decision-making to rely on
     * memory of teammate marked-status, making scanWithBall measurable.
     */
    private const PASS_FIRST_RULES = [
        [   // rules[0]: our team has ball
            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
            ['condition' => 'I has the ball',              'action' => 'Pass the ball'],
            ['condition' => 'The ball is in my side',      'action' => 'Go forward'],
            ['condition' => 'The ball is in other side',   'action' => 'Go forward'],
        ],
        [   // rules[1]: opponent has ball or no one has it
            ['condition' => 'I am near a rival',           'action' => 'Go to near rival'],
            ['condition' => 'The ball is in my side',      'action' => 'Go to the ball'],
            ['condition' => 'The ball is in other side',   'action' => 'Go to the ball'],
        ],
    ];

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Run N matches, return averaged metrics + raw goal totals for collapse check.
     */
    private function sweep(array|callable $teamA, array|callable $teamB, int $matches, int $ticks = self::TICKS_PER_MATCH): array
    {
        $results = [];
        for ($i = 0; $i < $matches; $i++) {
            $a = is_callable($teamA) ? ($teamA)() : $teamA;
            $b = is_callable($teamB) ? ($teamB)() : $teamB;
            $results[] = $this->runMatch($a, $b, $ticks);
        }
        $avg           = $this->averageResults($results);
        $avg['_goalsA'] = array_sum(array_column($results, 'goalsA'));
        $avg['_goalsB'] = array_sum(array_column($results, 'goalsB'));
        return $avg;
    }

    /**
     * Assert neither team collapsed possession or goals.
     * A failed assertion here is a balance warning, not a correctness failure.
     */
    private function assertNoCollapse(array $avg, string $label): void
    {
        $this->assertLessThan(
            self::COLLAPSE_POSSESSION,
            $avg['possessionA'],
            sprintf('[%s] Team A possession collapse: %.0f%% (threshold %.0f%%)',
                $label, $avg['possessionA'] * 100, self::COLLAPSE_POSSESSION * 100)
        );
        $this->assertLessThan(
            self::COLLAPSE_POSSESSION,
            $avg['possessionB'],
            sprintf('[%s] Team B possession collapse: %.0f%% (threshold %.0f%%)',
                $label, $avg['possessionB'] * 100, self::COLLAPSE_POSSESSION * 100)
        );

        $total = $avg['_goalsA'] + $avg['_goalsB'];
        if ($total > 0) {
            $share = max($avg['_goalsA'], $avg['_goalsB']) / $total;
            $this->assertLessThan(
                self::COLLAPSE_GOAL_SHARE,
                $share,
                sprintf('[%s] Goal collapse: %d-%d (%.0f%% share, threshold %.0f%%)',
                    $label,
                    $avg['_goalsA'], $avg['_goalsB'],
                    $share * 100,
                    self::COLLAPSE_GOAL_SHARE * 100)
            );
        }
    }

    // -------------------------------------------------------------------------
    // 1. MAX SPEED
    // -------------------------------------------------------------------------

    /**
     * Fast team (maxSpeed=1.0) vs slow team (maxSpeed=0.1).
     *
     * Formula: maxSpeed(1.0) = 3.0 u/tick  vs  maxSpeed(0.1) = 1.2 u/tick → 2.5×.
     *
     * Directional metric: controledBalls (loose-ball pickups).
     * After every shot or reset the ball appears at centre. The fast team
     * reaches it first and controls it more often. Possession time is NOT used
     * here because slow players barely drift from centre, so they can pick up
     * a reset ball just as quickly as a fast player that is far away — making
     * possession symmetric despite the speed gap. This is itself a useful
     * balance finding: pure speed does not create a runaway possession advantage
     * in a fast-shooting rule set.
     *
     * Collapse check: we still verify neither team dominates goals > COLLAPSE,
     * because the fast team does score more often (first to the ball each cycle).
     */
    public function test_maxspeed_1v01_controls_more_loose_balls_and_no_collapse(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['maxSpeed' => 1.0]),
            fn() => $this->makeTeam(['maxSpeed' => 0.1]),
            matches: 8
        );

        // Directional: fast team should win more loose-ball races
        $this->assertGreaterThan(
            $avg['teamB']['controledBalls'],
            $avg['teamA']['controledBalls'],
            sprintf('Fast team (maxSpeed=1.0) should control more loose balls than '
                . 'slow team (maxSpeed=0.1). Got A=%.1f vs B=%.1f per match.',
                $avg['teamA']['controledBalls'], $avg['teamB']['controledBalls'])
        );

        $this->assertNoCollapse($avg, 'maxSpeed 1.0 vs 0.1');
    }

    // -------------------------------------------------------------------------
    // 2. ENDURANCE
    // -------------------------------------------------------------------------

    /**
     * High-endurance team (endurance=1.0) vs low-endurance (endurance=0.1).
     * Run longer matches (5000 ticks) so strength depletion and recovery have
     * time to create a measurable gap in total distance traveled.
     *
     * Both teams start with full strength, but the high-endurance team
     * recovers faster during rest moments and maintains higher effective speed.
     *
     * Formula: recoveryRate(1.0) = 0.0005/tick  vs  recoveryRate(0.1) ≈ 0.0001/tick → 5×.
     */
    public function test_endurance_1v01_distance_advantage_over_long_match(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['endurance' => 1.0]),
            fn() => $this->makeTeam(['endurance' => 0.1]),
            matches: 5, ticks: 5000
        );

        $distA = $avg['teamA']['distanceTraveled'];
        $distB = $avg['teamB']['distanceTraveled'];

        // Directional: high-endurance team should travel farther
        $this->assertGreaterThan(
            $distB,
            $distA,
            sprintf('High-endurance team (endurance=1.0) should travel farther than '
                . 'low-endurance team (endurance=0.1). Got A=%.0f vs B=%.0f.',
                $distA, $distB)
        );
    }

    // -------------------------------------------------------------------------
    // 3. REACTION  (challenge attribute)
    // -------------------------------------------------------------------------

    /**
     * High-reaction team (reaction=1.0) vs zero-reaction team (reaction=0.0).
     *
     * Expected per challenge:
     *   - Team A (reaction=1.0): stealThreshold = max(0, 1.0 − 0.25) = 0.75 → ~75% steals
     *   - Team B (reaction=0.0): stealThreshold = max(0, 0.0 − 0.25) = 0    → 0% steals
     *
     * With ACTIVE_RULES challenges are rare (~3/match).
     * Using 15 matches gives ~45 challenge events: Team A expected ~34 steals,
     * Team B expected 0. That gap is large enough to assert a clear advantage.
     *
     * Also checks that the possession advantage doesn't collapse the game.
     */
    public function test_reaction_1v0_steals_more_and_no_collapse(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['reaction' => 1.0]),
            fn() => $this->makeTeam(['reaction' => 0.0]),
            matches: 15
        );

        $stealsA = $avg['teamA']['stealedBalls'];
        $stealsB = $avg['teamB']['stealedBalls'];

        // Directional: high-reaction team must steal more
        $this->assertGreaterThan(
            $stealsB,
            $stealsA,
            sprintf('High-reaction team (reaction=1.0) should steal more than zero-reaction team. '
                . 'Got A=%.1f vs B=%.1f steals/match.',
                $stealsA, $stealsB)
        );

        $this->assertNoCollapse($avg, 'reaction 1.0 vs 0.0');
    }

    // -------------------------------------------------------------------------
    // 4. DRIBBLE  (challenge attribute)
    // -------------------------------------------------------------------------

    /**
     * High-dribble team (dribble=1.0) vs zero-dribble team (dribble=0.0),
     * both against a neutral opponent (reaction=0.5).
     *
     * Expected per challenge when carrying the ball:
     *   - dribble=1.0 carrier: stealThreshold = max(0, 0.5 − 0.5) = 0    → 0 steals on carrier
     *   - dribble=0.0 carrier: stealThreshold = max(0, 0.5 − 0.0) = 0.5  → 50% steals on carrier
     *
     * So dribbleFailed for dribble=1.0 team should be much lower than for dribble=0.0 team.
     * We run both matchups against the same neutral team and compare.
     */
    public function test_dribble_1v0_loses_ball_less_often(): void
    {
        $avgHi = $this->sweep(
            fn() => $this->makeTeam(['dribble' => 1.0]),
            fn() => $this->makeTeam(),   // reaction=0.5, dribble=0.5
            matches: 15
        );

        $avgLo = $this->sweep(
            fn() => $this->makeTeam(['dribble' => 0.0]),
            fn() => $this->makeTeam(),
            matches: 15
        );

        $failedHi = $avgHi['teamA']['dribbleFailed'];  // high-dribble team
        $failedLo = $avgLo['teamA']['dribbleFailed'];  // low-dribble team

        // Directional: high-dribble team should lose the ball less often
        $this->assertLessThan(
            $failedLo,
            $failedHi,
            sprintf('High-dribble team (dribble=1.0) should have fewer dribbleFailed '
                . 'than low-dribble team (dribble=0.0). '
                . 'Got high=%.1f vs low=%.1f per match.',
                $failedHi, $failedLo)
        );
    }

    // -------------------------------------------------------------------------
    // 5. CONTROL  (ball pickup attribute)
    // -------------------------------------------------------------------------

    /**
     * High-control team (control=1.0) vs low-control (control=0.1).
     *
     * control determines the max ball speed at which a player can take
     * possession of a loose ball:
     *   control=1.0 → threshold 9.0 u/tick  (picks up almost everything)
     *   control=0.1 → threshold 3.6 u/tick  (only picks up slow balls)
     *
     * Directional metric: interceptedBalls (failed controls).
     * When a player reaches a ball that is moving faster than their threshold,
     * the ball bounces away and interceptedBalls++ is recorded for them.
     * The low-control team should fail more often when trying to pick up
     * fast-moving balls (shots, clearances) → more interceptedBalls.
     *
     * NOTE on controledBalls: fast balls land in the OPPONENT's area after a shot,
     * not the shooter's area. So the high-control TEAM is not usually near the
     * fast balls — the opponent (low control) is. Using controledBalls as the
     * directional metric would therefore be unreliable with ACTIVE_RULES.
     * interceptedBalls captures the same physics from the opposite direction.
     */
    public function test_control_1v01_low_control_fails_more_pickups(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['control' => 1.0]),
            fn() => $this->makeTeam(['control' => 0.1]),
            matches: 8
        );

        $interceptsA = $avg['teamA']['interceptedBalls'];  // high-control: should fail less
        $interceptsB = $avg['teamB']['interceptedBalls'];  // low-control:  should fail more

        // Directional: low-control team must fail more pickups
        $this->assertGreaterThan(
            $interceptsA,
            $interceptsB,
            sprintf('Low-control team (control=0.1) should have more failed pickups '
                . '(interceptedBalls) than high-control team (control=1.0). '
                . 'Got B=%.1f vs A=%.1f per match.',
                $interceptsB, $interceptsA)
        );

        $this->assertNoCollapse($avg, 'control 1.0 vs 0.1');
    }

    // -------------------------------------------------------------------------
    // 6. REACTION vs DRIBBLE  (symmetry of opposing attributes)
    // -------------------------------------------------------------------------

    /**
     * Reaction=1.0 team vs Dribble=1.0 team — are these two attributes
     * balanced against each other?
     *
     * Per-challenge math when Team A (reaction=1.0) challenges Team B (dribble=1.0):
     *   stealThreshold   = max(0, 1.0 − 0.5) = 0.50  → 50% steal
     *   takeoffThreshold = 1.0 − max(0, 1.0 − 0.5)  = 0.50
     *   Since stealThreshold == takeoffThreshold, the takeoff branch is UNREACHABLE:
     *   the ball either gets stolen (50%) or the dribble succeeds (50%) — never a takeoff.
     *
     * When Team B (dribble=1.0) challenges Team A (reaction=1.0 acting as carrier):
     *   stealThreshold   = max(0, 0.5 − 0.5) = 0     → 0% steal for B
     *   takeoffThreshold = 1.0 − max(0, 1.0 − 0.25) = 0.25 → 25% takeoff
     *   So dribbleDone(A as carrier) = 75%, dribbleDone(B as carrier) = 50%.
     *
     * Directional assertion: Team B (dribble=1.0) should have more dribbleDone
     * per challenge than Team A (dribble=0.5). We use dribbleDone because:
     *   - Steal comparison (50% vs 25%) needs ~50 matches for reliable 3σ detection
     *     with only ~1.5 challenges/team/match (ACTIVE_RULES limitation).
     *   - dribbleDone comparison (50% vs 75%) benefits from the SAME signal but
     *     comes from the opposite side of the same formula, giving equivalent signal.
     * Using 30 matches gives ~45 challenge events per team — enough to detect the gap.
     *
     * The collapse check is the primary balance concern: if reaction dominates
     * possession entirely, the attribute pairing is likely overpowered.
     */
    public function test_reaction1_vs_dribble1_advantage_and_no_collapse(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['reaction' => 1.0, 'dribble' => 0.5]),
            fn() => $this->makeTeam(['reaction' => 0.5, 'dribble' => 1.0]),
            matches: 30
        );

        // Directional: dribble=1.0 team should complete more successful dribbles
        // than dribble=0.5 team (50% vs 25% success rate per challenge as carrier).
        $dribbleDoneA = $avg['teamA']['dribbleDone'];  // reaction team (dribble=0.5 as carrier)
        $dribbleDoneB = $avg['teamB']['dribbleDone'];  // dribble team (dribble=1.0 as carrier)

        $this->assertGreaterThan(
            $dribbleDoneA,
            $dribbleDoneB,
            sprintf('Dribble team (dribble=1.0) should complete more dribbles than '
                . 'reaction team (dribble=0.5) when carrying the ball. '
                . 'Got B=%.2f vs A=%.2f dribbleDone/match. '
                . '(Expected ~2× ratio: 50%% vs 25%% success rate per challenge)',
                $dribbleDoneB, $dribbleDoneA)
        );

        // Collapse check — if reaction team dominates possession, attribute is overpowered
        $this->assertNoCollapse($avg, 'reaction=1.0 vs dribble=1.0');
    }

    // -------------------------------------------------------------------------
    // 7. STRENGTH  (depletion attribute)
    // -------------------------------------------------------------------------

    /**
     * High-strength team (strength=1.0) vs low-strength team (strength=0.0).
     * Both with endurance=0.0 (minimal recovery) to isolate strength from endurance.
     *
     * strength=1.0 → depletionRate = 0.0001/unit moved (10× slower than strength=0.0)
     * strength=0.0 → depletionRate = 0.001/unit  moved
     *
     * effectiveMaxSpeed = maxSpeed * (STRENGTH_SPEED_FLOOR + (1−FLOOR) * currentStrength)
     *   → exhausted player moves at 40% of their maxSpeed.
     *
     * With endurance=0.0 (recoveryRate ≈ 0.00005/tick) and ~2 units/tick:
     *   strength=1.0: net depletion ≈ −0.00015/tick → exhausted after ~6 700 ticks
     *   strength=0.0: net depletion ≈ −0.00195/tick → exhausted after  ~510 ticks
     *
     * Over 5 000 ticks the low-strength team runs at ~40% speed for most of the
     * match while the high-strength team maintains near-full speed throughout.
     *
     * Directional metric: distanceTraveled.
     */
    public function test_strength_1v0_distance_advantage_over_long_match(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['strength' => 1.0, 'endurance' => 0.0]),
            fn() => $this->makeTeam(['strength' => 0.0, 'endurance' => 0.0]),
            matches: 5, ticks: 5000
        );

        $distA = $avg['teamA']['distanceTraveled'];
        $distB = $avg['teamB']['distanceTraveled'];

        $this->assertGreaterThan(
            $distB,
            $distA,
            sprintf('High-strength team (strength=1.0) should travel farther than '
                . 'low-strength team (strength=0.0) when endurance=0.0. '
                . 'Got A=%.0f vs B=%.0f.',
                $distA, $distB)
        );
    }

    // -------------------------------------------------------------------------
    // 8. SCAN WITH BALL  (memory refresh rate while carrying)
    // -------------------------------------------------------------------------

    /**
     * High-scanWithBall team (scanWithBall=1.0) vs low-scanWithBall (scanWithBall=0.0),
     * using PASS_FIRST_RULES where the carrier always tries to pass first.
     *
     * With ACTIVE_RULES alone, scanWithBall has no measurable effect because the
     * carrier targets fixed goal coordinates (not memory-derived positions).
     * PASS_FIRST_RULES make memory freshness matter: Player::execute("Pass the ball")
     * filters teammates by !$p->marked.  Memory is updated for nearby players
     * (within assistDistance), but far teammates only refresh on a full scan.
     *
     *   scanWithBall=1.0 → refresh every ~80 ticks  → carrier quickly sees when a
     *                       far teammate shook off their marker → passes promptly.
     *   scanWithBall=0.0 → refresh every ~800 ticks → carrier keeps seeing stale
     *                       "marked=true" for far teammates → can't build the free-
     *                       player pool → holds the ball idle until the next refresh.
     *
     * A stationary carrier is easier for opponents to dispossess, so Team B also
     * loses the ball to challenges more often.
     *
     * Directional metric: passesAchieved — the passer's counter that increments
     * when a same-team player successfully controls their kicked ball.
     * 15 matches are used because the absolute pass count per match is low.
     */
    public function test_scanWithBall_1v0_achieves_more_passes(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['scanWithBall' => 1.0], self::PASS_FIRST_RULES),
            fn() => $this->makeTeam(['scanWithBall' => 0.0], self::PASS_FIRST_RULES),
            matches: 15
        );

        $achievedA = $avg['teamA']['passesAchieved'];
        $achievedB = $avg['teamB']['passesAchieved'];

        $this->assertGreaterThan(
            $achievedB,
            $achievedA,
            sprintf('High-scanWithBall team (scanWithBall=1.0) should complete more passes '
                . 'than low-scanWithBall team (scanWithBall=0.0). '
                . 'Got A=%.1f vs B=%.1f passesAchieved/match.',
                $achievedA, $achievedB)
        );
    }

    // -------------------------------------------------------------------------
    // 9. SCAN WITHOUT BALL  (memory refresh rate while not carrying)
    // -------------------------------------------------------------------------

    /**
     * High-scanWithoutBall team (scanWithoutBall=1.0) vs low (scanWithoutBall=0.0).
     *
     * Non-carriers use "Go to the ball" which sets target = $this->memory->ball.
     * The cached ball position is refreshed either on a full scan OR when the
     * player is within assistDistance (76 px) of the real ball.
     *
     * After every goal or shot the ball resets to centre (fieldWidth/2, fieldHeight/2).
     * Players far from centre have a stale ball position in memory:
     *
     *   scanWithoutBall=1.0 → refresh every ~60 ticks  → player redirects to centre
     *                         within one scan cycle after the reset.
     *   scanWithoutBall=0.0 → refresh every ~600 ticks → player keeps running to the
     *                         old ball position for most of the reset cycle, arriving
     *                         late to the new ball location.
     *
     * The fast-scan team wins the loose-ball race after each reset more consistently.
     *
     * Directional metric: controledBalls — direct count of successful ball pickups.
     */
    public function test_scanWithoutBall_1v0_controls_more_balls(): void
    {
        $avg = $this->sweep(
            fn() => $this->makeTeam(['scanWithoutBall' => 1.0]),
            fn() => $this->makeTeam(['scanWithoutBall' => 0.0]),
            matches: 10
        );

        $controlsA = $avg['teamA']['controledBalls'];
        $controlsB = $avg['teamB']['controledBalls'];

        $this->assertGreaterThan(
            $controlsB,
            $controlsA,
            sprintf('High-scanWithoutBall team (scanWithoutBall=1.0) should control more '
                . 'balls than low-scanWithoutBall team (scanWithoutBall=0.0). '
                . 'Got A=%.1f vs B=%.1f controledBalls/match.',
                $controlsA, $controlsB)
        );
    }
}
