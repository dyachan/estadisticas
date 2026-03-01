<?php

namespace App\Services;

/**
 * Formulas to convert normalized player attributes (0.0–1.0) to simulation values.
 * Edit the constants here to tune the overall feel of the simulation.
 *
 * Convention: 0 = weakest, 1 = best for all attributes.
 */
class PlayerFormulas
{
    // Tick range for memory refresh period with ball possession
    const SCAN_WITH_BALL_MIN = 50;   // ticks at value=1 (fast scan)
    const SCAN_WITH_BALL_MAX = 600;  // ticks at value=0 (slow scan)

    // Tick range for memory refresh period without ball possession
    const SCAN_WITHOUT_BALL_MIN = 80;   // ticks at value=1
    const SCAN_WITHOUT_BALL_MAX = 800;  // ticks at value=0

    /**
     * Convert a scan attribute (0–1) to memory refresh ticks when the player has the ball.
     */
    public static function scanPeriodWithBall(float $value): int
    {
        $value = max(0.0, min(1.0, $value));
        return (int) round(self::SCAN_WITH_BALL_MAX - (self::SCAN_WITH_BALL_MAX - self::SCAN_WITH_BALL_MIN) * $value);
    }

    /**
     * Convert a scan attribute (0–1) to memory refresh ticks when the player does not have the ball.
     */
    public static function scanPeriodWithoutBall(float $value): int
    {
        $value = max(0.0, min(1.0, $value));
        return (int) round(self::SCAN_WITHOUT_BALL_MAX - (self::SCAN_WITHOUT_BALL_MAX - self::SCAN_WITHOUT_BALL_MIN) * $value);
    }
}
