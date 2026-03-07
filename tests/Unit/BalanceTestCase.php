<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\MatchSimulation;

/**
 * Base class for balance / exploration tests.
 *
 * Provides:
 *  - makeTeam()      — build a 3-player team data array with uniform stats
 *  - runMatch()      — run N ticks and return structured metrics
 *  - runMatchSet()   — run M matches and return averaged metrics
 *
 * Rule set philosophy:
 *  rules[0] (our team has ball): carrier shoots immediately, teammates advance
 *  rules[1] (opponent has ball): nearest mark opponent, all others chase the ball
 *  defaultAction = "Stay in my zone" (set by MatchSimulation::loadTeams)
 *
 * This produces a realistic-enough game loop for statistical exploration
 * without requiring user-defined tactics.
 */
abstract class BalanceTestCase extends TestCase
{
    protected const TICKS_PER_MATCH  = MatchSimulation::TICKS_PER_MATCH;
    protected const MATCHES_PER_SET  = 5;

    /**
     * Active rule set used by all balance tests unless overridden.
     *
     * rules[0] — our team has possession:
     *   - Ball carrier near rival goal: shoot.
     *   - Ball carrier elsewhere: carry the ball toward the rival goal.
     *     This keeps the ball in play long enough for opponents to challenge.
     *   - Teammates: advance to support the carrier.
     *
     * rules[1] — opponent has possession (or no one has it):
     *   - Mark nearby opponents.
     *   - Everyone else chases the ball wherever it is.
     */
    protected const ACTIVE_RULES = [
        [   // rules[0]: our team has ball
            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
            ['condition' => 'I has the ball',              'action' => 'Go to rival goal'],
            ['condition' => 'The ball is in my side',      'action' => 'Go forward'],
            ['condition' => 'The ball is in other side',   'action' => 'Go forward'],
        ],
        [   // rules[1]: opponent has ball or no one has it
            ['condition' => 'I am near a rival',           'action' => 'Go to near rival'],
            ['condition' => 'The ball is in my side',      'action' => 'Go to the ball'],
            ['condition' => 'The ball is in other side',   'action' => 'Go to the ball'],
        ],
    ];

    /**
     * Standard 3-player formation zones (x%, y% of field size).
     * Matches the same geometry used by MatchSimulation::loadTeams for Team A.
     */
    private const DEFAULT_ZONES = [
        ['x' => 50, 'y' => 20],   // goalkeeper
        ['x' => 30, 'y' => 40],   // defender
        ['x' => 70, 'y' => 40],   // striker
    ];

    /**
     * Real team formations. When makeTeam() is called without explicit rules/zones,
     * one of these is picked at random. Each entry is a 3-player array where every
     * player carries its own rules and defaultZone.
     */
    private const FORMATIONS = [
        // Offensive FC
        [
            ['rules' => [[['condition' => 'I has the ball', 'action' => 'Pass the ball']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 50, 'y' => 20]],
            ['rules' => [[['condition' => 'The ball is in other side', 'action' => 'Shoot to goal'], ['condition' => 'I has the ball', 'action' => 'Go forward']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'The ball is in other side', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 30, 'y' => 60]],
            ['rules' => [[['condition' => 'The ball is in other side', 'action' => 'Shoot to goal'], ['condition' => 'I has the ball', 'action' => 'Go forward']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'The ball is in other side', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 65, 'y' => 80]],
        ],
        // Turtles
        [
            ['rules' => [[['condition' => 'I has the ball', 'action' => 'Pass the ball']], [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 50, 'y' => 15]],
            ['rules' => [[['condition' => 'I has the ball', 'action' => 'Pass the ball']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'I am near a rival', 'action' => 'Go to near rival']]], 'defaultZone' => ['x' => 35, 'y' => 35]],
            ['rules' => [[['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'], ['condition' => 'I has the ball', 'action' => 'Go forward']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'The ball is in other side', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 65, 'y' => 50]],
        ],
        // Team A
        [
            ['rules' => [[['condition' => 'I am marked', 'action' => 'Change side'], ['condition' => 'I has the ball', 'action' => 'Pass the ball']], [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 50, 'y' => 20]],
            ['rules' => [[['condition' => 'The ball is in other side', 'action' => 'Pass the ball'], ['condition' => 'I has the ball', 'action' => 'Go forward']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'I am near a rival', 'action' => 'Go to near rival']]], 'defaultZone' => ['x' => 50, 'y' => 50]],
            ['rules' => [[['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'], ['condition' => 'I has the ball', 'action' => 'Go forward'], ['condition' => 'I am near a rival', 'action' => 'Change side']], [['condition' => 'The ball is in other side', 'action' => 'Go to the ball'], ['condition' => 'Rival in my side', 'action' => 'Go to the ball'], ['condition' => 'I am near a rival', 'action' => 'Change side']]], 'defaultZone' => ['x' => 75, 'y' => 70]],
        ],
        // Pomarola Mecánica
        [
            ['rules' => [[['condition' => 'I has the ball', 'action' => 'Pass the ball']], [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 50, 'y' => 20]],
            ['rules' => [[['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'], ['condition' => 'I am near a rival', 'action' => 'Pass the ball'], ['condition' => 'I has the ball', 'action' => 'Go forward']], [['condition' => 'The ball is in my side', 'action' => 'Go to the ball'], ['condition' => 'I am near a rival', 'action' => 'Go to near rival']]], 'defaultZone' => ['x' => 50, 'y' => 40]],
            ['rules' => [[['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'], ['condition' => 'I has the ball', 'action' => 'Go forward'], ['condition' => 'I am marked', 'action' => 'Change side']], [['condition' => 'The ball is in other side', 'action' => 'Go to the ball']]], 'defaultZone' => ['x' => 50, 'y' => 60]],
        ],
    ];

    // -------------------------------------------------------------------------
    // PUBLIC HELPERS
    // -------------------------------------------------------------------------

    /**
     * Build a team data array compatible with MatchSimulation::loadTeams().
     *
     * @param array      $stats    Stat overrides (0–1 normalized). Any key not
     *                             provided defaults to 0.5.
     * @param array|null $rules    Rule set. Defaults to ACTIVE_RULES.
     * @param array|null $zones    defaultZone per player [{x,y}, ...]. Must have
     *                             exactly 3 entries if provided.
     */
    protected function makeTeam(
        array  $stats = [],
        ?array $rules = null,
        ?array $zones = null
    ): array {
        $defaults = [
            'maxSpeed'        => 0.5,
            'accuracy'        => 0.5,
            'control'         => 0.5,
            'reaction'        => 0.5,
            'dribble'         => 0.5,
            'strength'        => 0.5,
            'endurance'       => 0.5,
            'scanWithBall'    => 0.5,
            'scanWithoutBall' => 0.5,
        ];

        $resolvedStats = array_merge($defaults, $stats);

        // No explicit rules or zones → pick one of the real formations at random.
        if ($rules === null && $zones === null) {
            $formation = self::FORMATIONS[array_rand(self::FORMATIONS)];
            $players   = [];
            foreach ($formation as $i => $playerDef) {
                $players[] = array_merge($resolvedStats, [
                    'name'        => 'P' . ($i + 1),
                    'rules'       => $playerDef['rules'],
                    'defaultZone' => $playerDef['defaultZone'],
                ]);
            }
            return ['players' => $players];
        }

        $resolvedRules = $rules ?? self::ACTIVE_RULES;
        $resolvedZones = $zones ?? self::DEFAULT_ZONES;

        $players = [];
        foreach ($resolvedZones as $i => $zone) {
            $players[] = array_merge($resolvedStats, [
                'name'        => 'P' . ($i + 1),
                'rules'       => $resolvedRules,
                'defaultZone' => $zone,
            ]);
        }

        return ['players' => $players];
    }

    /**
     * Run a single match and return structured metrics.
     *
     * Returned keys:
     *   goalsA, goalsB          — goal count per team
     *   possessionA, possessionB — fraction of total time (0–1)
     *   totalChallenges          — sum of all challenge events
     *   stealRate                — steals / totalChallenges (0–1)
     *   teamA, teamB             — aggregated per-team stats (see aggregateTeam)
     */
    protected function runMatch(
        array $teamA,
        array $teamB,
        int   $ticks = self::TICKS_PER_MATCH
    ): array {
        $match = new MatchSimulation(400, 600);
        $match->loadTeams($teamA, $teamB);

        for ($t = 0; $t < $ticks; $t++) {
            $match->update();
        }

        return $this->extractMetrics($match);
    }

    /**
     * Run M matches and return averaged metrics.
     * Useful for smoothing out the variance of individual matches.
     */
    protected function runMatchSet(
        array|callable $teamA,
        array|callable $teamB,
        int            $matches = self::MATCHES_PER_SET,
        int            $ticks   = self::TICKS_PER_MATCH
    ): array {
        $results = [];
        for ($i = 0; $i < $matches; $i++) {
            $a = is_callable($teamA) ? ($teamA)() : $teamA;
            $b = is_callable($teamB) ? ($teamB)() : $teamB;
            $results[] = $this->runMatch($a, $b, $ticks);
        }
        return $this->averageResults($results);
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    private function extractMetrics(MatchSimulation $match): array
    {
        $s     = $match->getSummary();
        $total = $s['totalTime'];

        $aggA = $this->aggregateTeam($s['TeamA']);
        $aggB = $this->aggregateTeam($s['TeamB']);

        $totalSteals     = $aggA['stealedBalls']         + $aggB['stealedBalls'];
        $totalChallenges = $aggA['challengedRivalWithBall'] + $aggB['challengedRivalWithBall'];

        return [
            'goalsA'          => $s['GoalsA'],
            'goalsB'          => $s['GoalsB'],
            'possessionA'     => $total > 0 ? $s['possessionA'] / $total : 0.0,
            'possessionB'     => $total > 0 ? $s['possessionB'] / $total : 0.0,
            'totalChallenges' => $totalChallenges,
            'stealRate'       => $totalChallenges > 0 ? $totalSteals / $totalChallenges : 0.0,
            'teamA'           => $aggA,
            'teamB'           => $aggB,
        ];
    }

    private function aggregateTeam(array $players): array
    {
        $keys = [
            'distanceTraveled', 'distanceTraveledWithBall',
            'controledBalls', 'interceptedBalls',
            'passesMade', 'passesAchieved', 'shootMade', 'goals',
            'stealedBalls', 'takedoffBalls',
            'dribbleDone', 'dribbleFailed',
            'challengedMeWithBall', 'challengedRivalWithBall',
        ];

        $result = [];
        foreach ($keys as $k) {
            $result[$k] = (float) array_sum(array_column($players, $k));
        }
        return $result;
    }

    protected function averageResults(array $results): array
    {
        $n = count($results);

        $topKeys = ['goalsA', 'goalsB', 'possessionA', 'possessionB', 'totalChallenges', 'stealRate'];
        $avg     = [];
        foreach ($topKeys as $k) {
            $avg[$k] = array_sum(array_column($results, $k)) / $n;
        }

        foreach (['teamA', 'teamB'] as $side) {
            $teamRows = array_column($results, $side);
            foreach (array_keys($teamRows[0]) as $k) {
                $avg[$side][$k] = array_sum(array_column($teamRows, $k)) / $n;
            }
        }

        return $avg;
    }
}
