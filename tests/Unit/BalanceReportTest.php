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
 *   2. test_attribute_correlation_report — Pearson r of attributes vs all outcome metrics
 *   3. test_attribute_extremes_report    — hi(0.9) vs lo(0.1) summary per attribute
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
     * Five sub-tables per attribute covering all PlayerSummary stats:
     *   Table A — goals / possession
     *   Table B — shooting / passing
     *   Table C — ball control (controlled, intercepted, steals, takeoffs)
     *   Table D — dribble / challenge
     *   Table E — distance
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
     * Outcomes cover all PlayerSummary stats for Team A plus goal/poss differentials,
     * split into three grouped sub-tables for readability.
     */
    public function test_attribute_correlation_report(): void
    {
        $this->sectionHeader(
            'REPORT 2 — ATTRIBUTE CORRELATION WITH OUTCOMES',
            self::CORR_SAMPLES . ' random Team A configs vs neutral Team B (all 0.5).',
            'Pearson r ∈ [−1, 1].  |r| > 0.5 = strong | 0.3–0.5 = medium | < 0.3 = weak.'
        );

        $rows = [];

        for ($i = 0; $i < self::CORR_SAMPLES; $i++) {
            $attrs = [];
            foreach (self::ATTRIBUTES as $attr) {
                $attrs[$attr] = round(mt_rand(10, 90) / 100, 2);
            }
            $r      = $this->runMatch($this->makeTeam($attrs), $this->makeTeam());
            $rows[] = array_merge($attrs, [
                'goalDiff'  => $r['goalsA']      - $r['goalsB'],
                'possDiff'  => $r['possessionA'] - $r['possessionB'],
                'shotsA'    => $r['teamA']['shootMade'],
                'pasMadeA'  => $r['teamA']['passesMade'],
                'pasAchA'   => $r['teamA']['passesAchieved'],
                'ctrlA'     => $r['teamA']['controledBalls'],
                'intcpA'    => $r['teamA']['interceptedBalls'],
                'stealsA'   => $r['teamA']['stealedBalls'],
                'tkoffA'    => $r['teamA']['takedoffBalls'],
                'dribDoneA' => $r['teamA']['dribbleDone'],
                'dribFailA' => $r['teamA']['dribbleFailed'],
                'chMeA'     => $r['teamA']['challengedMeWithBall'],
                'chRivA'    => $r['teamA']['challengedRivalWithBall'],
                'distA'     => $r['teamA']['distanceTraveled'],
                'distBallA' => $r['teamA']['distanceTraveledWithBall'],
            ]);
        }

        $groups = [
            'Scoring & Passing' => [
                'goalDiff' => 'Goal-Diff',
                'possDiff' => 'Poss-Diff',
                'shotsA'   => 'Shots-A',
                'pasMadeA' => 'PasMade-A',
                'pasAchA'  => 'PasAch-A',
            ],
            'Ball Possession' => [
                'ctrlA'     => 'Ctrl-A',
                'intcpA'    => 'Intcp-A',
                'stealsA'   => 'Steals-A',
                'tkoffA'    => 'TkOff-A',
                'dribDoneA' => 'DribDone-A',
                'dribFailA' => 'DribFail-A',
            ],
            'Challenges & Distance' => [
                'chMeA'     => 'ChMe-A',
                'chRivA'    => 'ChRiv-A',
                'distA'     => 'Dist-A',
                'distBallA' => 'DistBall-A',
            ],
        ];

        foreach ($groups as $groupName => $outcomes) {
            $this->out("\n  [$groupName]\n");
            $this->printCorrelationGroup($rows, $outcomes);
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
     * Four sub-tables cover all PlayerSummary stats:
     *   Table 1 — goals / possession / verdict
     *   Table 2 — shooting / passing
     *   Table 3 — ball control (controlled, intercepted, steals, takeoffs)
     *   Table 4 — dribble / challenge
     *   Table 5 — distance
     */
    public function test_attribute_extremes_report(): void
    {
        $this->sectionHeader(
            'REPORT 3 — ATTRIBUTE EXTREMES: HIGH (0.9) vs LOW (0.1)',
            'All other attributes = 0.5.  6 matches per attribute.  Hi = attr 0.9 team.'
        );

        // Collect all averages first so we can reuse them across sub-tables.
        $avgs = [];
        foreach (self::ATTRIBUTES as $attr) {
            $avgs[$attr] = $this->runMatchSet(
                fn() => $this->makeTeam([$attr => 0.9]),
                fn() => $this->makeTeam([$attr => 0.1]),
                6
            );
        }

        // ── Sub-table 1: Goals & Possession ──────────────────────────────────
        $this->out(sprintf("\n  %-14s │ %-7s │ %-7s │ %-9s │ %-9s │ %-8s │ %s\n",
            'Attribute', 'Gls-Hi', 'Gls-Lo', 'Poss-Hi%', 'Poss-Lo%', 'G-Diff', 'Result'));
        $this->out('  ' . str_repeat('─', 72) . "\n");
        foreach (self::ATTRIBUTES as $attr) {
            $avg     = $avgs[$attr];
            $diff    = $avg['goalsA'] - $avg['goalsB'];
            $verdict = $diff > 0.3 ? 'HIGH wins' : ($diff < -0.3 ? 'LOW wins' : 'EVEN');
            $this->out(sprintf("  %-14s │ %-7s │ %-7s │ %-9s │ %-9s │ %-8s │ %s\n",
                $attr,
                number_format($avg['goalsA'], 1),
                number_format($avg['goalsB'], 1),
                number_format($avg['possessionA'] * 100, 1) . '%',
                number_format($avg['possessionB'] * 100, 1) . '%',
                sprintf('%+.1f', $diff),
                $verdict
            ));
        }

        // ── Sub-table 2: Shooting & Passing ──────────────────────────────────
        $this->printExtremesTable('Shooting & Passing', [
            'Shots'   => 'shootMade',
            'PasMade' => 'passesMade',
            'PasAch'  => 'passesAchieved',
        ], $avgs);

        // ── Sub-table 3: Ball Control ─────────────────────────────────────────
        $this->printExtremesTable('Ball Control', [
            'Ctrl'  => 'controledBalls',
            'Intcp' => 'interceptedBalls',
            'Steal' => 'stealedBalls',
            'TkOff' => 'takedoffBalls',
        ], $avgs);

        // ── Sub-table 4: Dribble & Challenge ──────────────────────────────────
        $this->printExtremesTable('Dribble & Challenge', [
            'DribDone' => 'dribbleDone',
            'DribFail' => 'dribbleFailed',
            'ChMe'     => 'challengedMeWithBall',
            'ChRiv'    => 'challengedRivalWithBall',
        ], $avgs);

        // ── Sub-table 5: Distance ─────────────────────────────────────────────
        $this->printExtremesTable('Distance', [
            'Dist'     => 'distanceTraveled',
            'DistBall' => 'distanceTraveledWithBall',
        ], $avgs, 0);

        $this->out("\n");
        $this->assertTrue(true);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Run a full attribute sweep for one attribute and print five sub-tables
     * covering all PlayerSummary stats.
     */
    private function printAttributeSweep(string $attr): void
    {
        $data = [];
        foreach (self::SWEEP_VALUES as $val) {
            $data[(string)$val] = $this->runMatchSet(
                fn() => $this->makeTeam([$attr => $val]),
                fn() => $this->makeTeam(),
                self::SWEEP_MATCHES
            );
        }

        $this->out("\n  ── $attr ──\n");

        $this->printSweepTable('Goals & Possession', [
            ['label' => 'Gls-A',   'w' => 6,  'fmt' => fn($a) => number_format($a['goalsA'], 1)],
            ['label' => 'Gls-B',   'w' => 6,  'fmt' => fn($a) => number_format($a['goalsB'], 1)],
            ['label' => 'G-Diff',  'w' => 7,  'fmt' => fn($a) => sprintf('%+.1f', $a['goalsA'] - $a['goalsB'])],
            ['label' => 'Poss-A%', 'w' => 9,  'fmt' => fn($a) => number_format($a['possessionA'] * 100, 1) . '%'],
            ['label' => 'Poss-B%', 'w' => 9,  'fmt' => fn($a) => number_format($a['possessionB'] * 100, 1) . '%'],
        ], $data);

        $this->printSweepTable('Shooting & Passing', [
            ['label' => 'Shots-A',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['shootMade'], 1)],
            ['label' => 'Shots-B',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['shootMade'], 1)],
            ['label' => 'PasMd-A',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['passesMade'], 1)],
            ['label' => 'PasMd-B',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['passesMade'], 1)],
            ['label' => 'PasAc-A',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['passesAchieved'], 1)],
            ['label' => 'PasAc-B',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['passesAchieved'], 1)],
        ], $data);

        $this->printSweepTable('Ball Control', [
            ['label' => 'Ctrl-A',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['controledBalls'], 1)],
            ['label' => 'Ctrl-B',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['controledBalls'], 1)],
            ['label' => 'Intcp-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['interceptedBalls'], 1)],
            ['label' => 'Intcp-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['interceptedBalls'], 1)],
            ['label' => 'Steal-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['stealedBalls'], 1)],
            ['label' => 'Steal-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['stealedBalls'], 1)],
            ['label' => 'TkOff-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['takedoffBalls'], 1)],
            ['label' => 'TkOff-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['takedoffBalls'], 1)],
        ], $data);

        $this->printSweepTable('Dribble & Challenge', [
            ['label' => 'DribD-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['dribbleDone'], 1)],
            ['label' => 'DribD-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['dribbleDone'], 1)],
            ['label' => 'DribF-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['dribbleFailed'], 1)],
            ['label' => 'DribF-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['dribbleFailed'], 1)],
            ['label' => 'ChMe-A',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['challengedMeWithBall'], 1)],
            ['label' => 'ChMe-B',  'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['challengedMeWithBall'], 1)],
            ['label' => 'ChRiv-A', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamA']['challengedRivalWithBall'], 1)],
            ['label' => 'ChRiv-B', 'w' => 8, 'fmt' => fn($a) => number_format($a['teamB']['challengedRivalWithBall'], 1)],
        ], $data);

        $this->printSweepTable('Distance', [
            ['label' => 'Dist-A',     'w' => 9,  'fmt' => fn($a) => number_format($a['teamA']['distanceTraveled'], 0)],
            ['label' => 'Dist-B',     'w' => 9,  'fmt' => fn($a) => number_format($a['teamB']['distanceTraveled'], 0)],
            ['label' => 'DistBall-A', 'w' => 10, 'fmt' => fn($a) => number_format($a['teamA']['distanceTraveledWithBall'], 0)],
            ['label' => 'DistBall-B', 'w' => 10, 'fmt' => fn($a) => number_format($a['teamB']['distanceTraveledWithBall'], 0)],
        ], $data);
    }

    /**
     * Print one sweep sub-table.
     *
     * @param array $cols  Each element: ['label' => string, 'w' => int, 'fmt' => callable($avg): string]
     * @param array $data  Keyed by string sweep value → runMatchSet result.
     */
    private function printSweepTable(string $label, array $cols, array $data): void
    {
        $lineW = 5; // 'Val' column
        foreach ($cols as $col) {
            $lineW += 3 + $col['w']; // ' │ ' + content
        }

        $this->out("\n  $label\n");

        $header = sprintf("  %-5s", 'Val');
        foreach ($cols as $col) {
            $header .= sprintf(" │ %-{$col['w']}s", $col['label']);
        }
        $this->out($header . "\n" . '  ' . str_repeat('─', $lineW) . "\n");

        foreach (self::SWEEP_VALUES as $val) {
            $avg = $data[(string)$val];
            $row = sprintf("  %-5s", $val);
            foreach ($cols as $col) {
                $row .= sprintf(" │ %-{$col['w']}s", ($col['fmt'])($avg));
            }
            $this->out($row . "\n");
        }
    }

    /**
     * Print one extremes sub-table (Hi = attr 0.9 team, Lo = attr 0.1 team).
     *
     * @param array  $stats  ['ColumnLabel' => 'statKey', ...]
     * @param array  $avgs   ['attrName' => runMatchSet result, ...]
     * @param int    $decimals  Decimal places for number_format (default 1).
     */
    private function printExtremesTable(string $label, array $stats, array $avgs, int $decimals = 1): void
    {
        // colW must fit the longest "Label-Hi" / "Label-Lo" header string.
        $colW = max(7, max(array_map(fn($l) => strlen($l) + 3, array_keys($stats))));

        $lineW = 14; // 'Attribute' column
        foreach ($stats as $ignored) {
            $lineW += (3 + $colW) * 2; // two cols (Hi + Lo) per stat
        }

        $this->out("\n  $label\n");

        $header = sprintf("  %-14s", 'Attribute');
        foreach (array_keys($stats) as $statLabel) {
            $header .= sprintf(" │ %-{$colW}s │ %-{$colW}s", $statLabel . '-Hi', $statLabel . '-Lo');
        }
        $this->out($header . "\n" . '  ' . str_repeat('─', $lineW) . "\n");

        foreach (self::ATTRIBUTES as $attr) {
            $avg = $avgs[$attr];
            $row = sprintf("  %-14s", $attr);
            foreach ($stats as $statKey) {
                $row .= sprintf(" │ %-{$colW}s │ %-{$colW}s",
                    number_format($avg['teamA'][$statKey], $decimals),
                    number_format($avg['teamB'][$statKey], $decimals)
                );
            }
            $this->out($row . "\n");
        }
    }

    /**
     * Print one correlation sub-table (attributes as rows, outcomes as columns).
     *
     * @param array $rows      Collected sample rows with attribute and outcome keys.
     * @param array $outcomes  ['rowKey' => 'ColumnLabel', ...]
     */
    private function printCorrelationGroup(array $rows, array $outcomes): void
    {
        $colW  = 10;
        $lineW = 14 + ($colW + 3) * count($outcomes);

        $header = sprintf("  %-14s", 'Attribute');
        foreach ($outcomes as $label) {
            $header .= sprintf(" │ %-{$colW}s", $label);
        }
        $this->out($header . "\n" . '  ' . str_repeat('─', $lineW) . "\n");

        foreach (self::ATTRIBUTES as $attr) {
            $xs  = array_column($rows, $attr);
            $row = sprintf("  %-14s", $attr);
            foreach (array_keys($outcomes) as $outcome) {
                $r   = $this->pearson($xs, array_column($rows, $outcome));
                $row .= sprintf(" │ %-{$colW}s", sprintf('%+.2f', $r));
            }
            $this->out($row . "\n");
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
