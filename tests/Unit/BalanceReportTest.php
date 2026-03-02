<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;

/**
 * Balance report tests — exploration / analytics output, no hard assertions.
 *
 * These tests write formatted tables directly to STDOUT instead of asserting
 * pass/fail. They always pass. They are excluded from the standard test suite
 * via phpunit.xml @group exclusion.
 *
 * Run with:
 *   php artisan test --group report --no-coverage
 *   ./vendor/bin/phpunit --group report --no-coverage
 *
 * Reports:
 *   1. test_attribute_sweep_report       — each attribute swept 0.1→0.9 vs neutral team
 *   2. test_attribute_correlation_report — Pearson r of attributes vs outcome metrics
 *   3. test_attribute_extremes_report    — hi(0.9) vs lo(0.1) quick summary per attribute
 *
 * NOTE on endurance / strength: both affect distanceTraveled; their signal is
 * most pronounced over long matches. TICKS_PER_MATCH is now pulled from
 * MatchSimulation::TICKS_PER_MATCH (5 000), so the effect is fully visible here.
 *
 * NOTE on scanWithBall: with ACTIVE_RULES the carrier targets fixed goal
 * coordinates and proximity-updates the ball position, so scan period has no
 * measurable effect in these reports. Its effect only appears with a passing-
 * based rule set (see test_scanWithBall_1v0_achieves_more_passes).
 */
#[Group('report')]
class BalanceReportTest extends BalanceTestCase
{
    // ── Configuration ─────────────────────────────────────────────────────────

    /** Attribute values used in the sweep (Report 1). */
    private const SWEEP_VALUES  = [0.1, 0.3, 0.5, 0.7, 0.9];

    /** Matches averaged per (attribute, value) data point in Report 1. */
    private const SWEEP_MATCHES = 10;

    /** Random team samples for Pearson correlation (Report 2). */
    private const CORR_SAMPLES  = 60;

    /** Attributes to analyse. */
    private const ATTRIBUTES = [
        'maxSpeed', 'accuracy', 'control',
        'reaction', 'dribble', 'endurance',
        'strength', 'scanWithBall', 'scanWithoutBall',
    ];

    // =========================================================================
    // Report 1 — Attribute sweep
    // =========================================================================

    /**
     * For each attribute, sweep its value from 0.1 to 0.9 while all other
     * attributes stay at 0.5. Team B is always a neutral (all-0.5) opponent.
     *
     * Two sub-tables per attribute:
     *   Table A — standard match outcomes: goals, possession, shots.
     *   Table B — ball-event metrics: controledBalls, steals, dribbleFailed.
     *
     * Look for: which rows in G-Diff or Poss-A% change noticeably → that
     * attribute meaningfully affects match outcomes.
     */
    public function test_attribute_sweep_report(): void
    {
        $this->sectionHeader(
            'REPORT 1 — ATTRIBUTE SWEEP',
            'Team A: swept attribute | Team B: all attributes = 0.5',
            self::SWEEP_MATCHES . ' matches × ' . self::TICKS_PER_MATCH . ' ticks per data point.'
        );

        foreach (self::ATTRIBUTES as $attr) {
            $this->printAttributeSweep($attr);
        }

        $this->assertTrue(true);
    }

    // =========================================================================
    // Report 2 — Attribute correlation
    // =========================================================================

    /**
     * Generate CORR_SAMPLES random team configurations for Team A, each vs a
     * neutral Team B. Compute Pearson r between each attribute and each outcome.
     *
     * High |r| → the attribute strongly predicts that outcome across random teams.
     *   |r| > 0.5  strong
     *   |r| 0.3–0.5  medium
     *   |r| < 0.3  weak / noisy
     *
     * Outcomes tracked: goal differential, possession differential, shots by A,
     * controlled balls by A, steals by A, dribbleFailed by A, distance by A.
     */
    public function test_attribute_correlation_report(): void
    {
        $this->sectionHeader(
            'REPORT 2 — ATTRIBUTE CORRELATION WITH OUTCOMES',
            self::CORR_SAMPLES . ' random Team A configs vs neutral Team B (all 0.5).',
            'Pearson r ∈ [−1, 1].  |r| > 0.5 = strong | 0.3–0.5 = medium | < 0.3 = weak.'
        );

        $neutral = $this->makeTeam();
        $rows    = [];

        for ($i = 0; $i < self::CORR_SAMPLES; $i++) {
            $attrs = [];
            foreach (self::ATTRIBUTES as $attr) {
                $attrs[$attr] = round(mt_rand(10, 90) / 100, 2);
            }
            $result = $this->runMatch($this->makeTeam($attrs), $neutral);
            $rows[] = array_merge($attrs, [
                'goalDiff'   => $result['goalsA']      - $result['goalsB'],
                'possDiff'   => $result['possessionA'] - $result['possessionB'],
                'shotsA'     => $result['teamA']['shootMade'],
                'ctrlBallsA' => $result['teamA']['controledBalls'],
                'stealsA'    => $result['teamA']['stealedBalls'],
                'dribFailA'  => $result['teamA']['dribbleFailed'],
                'distA'      => $result['teamA']['distanceTraveled'],
            ]);
        }

        $outcomes = [
            'goalDiff'   => 'Goal-Diff',
            'possDiff'   => 'Poss-Diff',
            'shotsA'     => 'Shots-A',
            'ctrlBallsA' => 'CtrlBalls',
            'stealsA'    => 'Steals-A',
            'dribFailA'  => 'DribFail-A',
            'distA'      => 'Dist-A',
        ];

        $colW = 11;

        $this->out("\n");
        $this->out(sprintf("  %-14s", 'Attribute'));
        foreach ($outcomes as $label) {
            $this->out(sprintf(" │ %-{$colW}s", $label));
        }
        $this->out("\n  " . str_repeat('─', 14 + ($colW + 3) * count($outcomes)) . "\n");

        foreach (self::ATTRIBUTES as $attr) {
            $xs = array_column($rows, $attr);
            $this->out(sprintf("  %-14s", $attr));
            foreach (array_keys($outcomes) as $outcome) {
                $r = $this->pearson($xs, array_column($rows, $outcome));
                $this->out(sprintf(" │ %-{$colW}s", sprintf('%+.2f', $r)));
            }
            $this->out("\n");
        }

        $this->out("\n");
        $this->assertTrue(true);
    }

    // =========================================================================
    // Report 3 — Extremes summary
    // =========================================================================

    /**
     * Quick comparison: attribute=0.9 (HIGH team) vs attribute=0.1 (LOW team).
     * All other attributes = 0.5 on both sides. 6 matches per attribute.
     *
     * Goal: identify which attributes create the most lopsided matchups when
     * pushed to extremes. A "EVEN" result means the attribute does not strongly
     * determine match outcomes on its own (even at extreme values).
     */
    public function test_attribute_extremes_report(): void
    {
        $this->sectionHeader(
            'REPORT 3 — ATTRIBUTE EXTREMES: HIGH (0.9) vs LOW (0.1)',
            'All other attributes = 0.5.  6 matches per attribute.'
        );

        $this->out(sprintf("\n  %-14s │ %-7s │ %-7s │ %-9s │ %-9s │ %-8s │ %s\n",
            'Attribute', 'Gls-Hi', 'Gls-Lo', 'Poss-Hi%', 'Poss-Lo%', 'G-Diff', 'Result'));
        $this->out('  ' . str_repeat('─', 72) . "\n");

        foreach (self::ATTRIBUTES as $attr) {
            $avg  = $this->runMatchSet(
                $this->makeTeam([$attr => 0.9]),
                $this->makeTeam([$attr => 0.1]),
                6
            );
            $diff   = $avg['goalsA'] - $avg['goalsB'];
            $result = $diff > 0.3 ? 'HIGH wins' : ($diff < -0.3 ? 'LOW wins' : 'EVEN');

            $this->out(sprintf("  %-14s │ %-7s │ %-7s │ %-9s │ %-9s │ %-8s │ %s\n",
                $attr,
                number_format($avg['goalsA'], 1),
                number_format($avg['goalsB'], 1),
                number_format($avg['possessionA'] * 100, 1) . '%',
                number_format($avg['possessionB'] * 100, 1) . '%',
                sprintf('%+.1f', $diff),
                $result
            ));
        }

        $this->out("\n");
        $this->assertTrue(true);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Run a full attribute sweep for one attribute and print two sub-tables.
     */
    private function printAttributeSweep(string $attr): void
    {
        $neutral = $this->makeTeam();

        // Collect results indexed by string key to avoid float→int key coercion.
        $data = [];
        foreach (self::SWEEP_VALUES as $val) {
            $data[(string)$val] = $this->runMatchSet(
                $this->makeTeam([$attr => $val]),
                $neutral,
                self::SWEEP_MATCHES
            );
        }

        $this->out("\n  ── $attr ──\n");

        // Table A: goals / possession / shots
        $this->out(sprintf(
            "  %-5s │ %-6s │ %-6s │ %-7s │ %-9s │ %-9s │ %-8s │ %-8s\n",
            'Val', 'Gls-A', 'Gls-B', 'G-Diff', 'Poss-A%', 'Poss-B%', 'Shots-A', 'Shots-B'
        ));
        $this->out('  ' . str_repeat('─', 76) . "\n");

        foreach (self::SWEEP_VALUES as $val) {
            $avg = $data[(string)$val];
            $this->out(sprintf(
                "  %-5s │ %-6s │ %-6s │ %-7s │ %-9s │ %-9s │ %-8s │ %-8s\n",
                $val,
                number_format($avg['goalsA'], 1),
                number_format($avg['goalsB'], 1),
                sprintf('%+.1f', $avg['goalsA'] - $avg['goalsB']),
                number_format($avg['possessionA'] * 100, 1) . '%',
                number_format($avg['possessionB'] * 100, 1) . '%',
                number_format($avg['teamA']['shootMade'], 1),
                number_format($avg['teamB']['shootMade'], 1)
            ));
        }

        // Table B: ball-event metrics
        $this->out(sprintf(
            "\n  %-5s │ %-10s │ %-10s │ %-10s │ %-10s │ %-11s │ %-11s\n",
            'Val', 'Ctrl-A', 'Ctrl-B', 'Steals-A', 'Steals-B', 'DribFail-A', 'DribFail-B'
        ));
        $this->out('  ' . str_repeat('─', 80) . "\n");

        foreach (self::SWEEP_VALUES as $val) {
            $avg = $data[(string)$val];
            $this->out(sprintf(
                "  %-5s │ %-10s │ %-10s │ %-10s │ %-10s │ %-11s │ %-11s\n",
                $val,
                number_format($avg['teamA']['controledBalls'], 1),
                number_format($avg['teamB']['controledBalls'], 1),
                number_format($avg['teamA']['stealedBalls'], 1),
                number_format($avg['teamB']['stealedBalls'], 1),
                number_format($avg['teamA']['dribbleFailed'], 1),
                number_format($avg['teamB']['dribbleFailed'], 1)
            ));
        }
    }

    /**
     * Pearson correlation coefficient between two equal-length float arrays.
     * Returns 0.0 when the denominator is zero (constant input).
     */
    private function pearson(array $x, array $y): float
    {
        $n = count($x);
        if ($n < 2) {
            return 0.0;
        }

        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;

        $num = $dx2 = $dy2 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx   = $x[$i] - $mx;
            $dy   = $y[$i] - $my;
            $num += $dx * $dy;
            $dx2 += $dx ** 2;
            $dy2 += $dy ** 2;
        }

        $denom = sqrt($dx2 * $dy2);
        return $denom > 0.0 ? $num / $denom : 0.0;
    }

    /**
     * Print a section header with box-drawing chars.
     */
    private function sectionHeader(string ...$lines): void
    {
        $this->out("\n" . str_repeat('═', 80) . "\n");
        foreach ($lines as $line) {
            $this->out("  $line\n");
        }
        $this->out(str_repeat('═', 80) . "\n");
    }

    /**
     * Write directly to STDOUT, bypassing PHPUnit's output buffer.
     */
    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
