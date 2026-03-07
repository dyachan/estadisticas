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
    // -------------------------------------------------------------------------
    // SCAN PERIOD
    // -------------------------------------------------------------------------
    const SCAN_WITH_BALL_MIN = 80;   // ticks at value=1 (fast scan)
    const SCAN_WITH_BALL_MAX = 2000;  // ticks at value=0 (slow scan)

    const SCAN_WITHOUT_BALL_MIN = 60;   // ticks at value=1
    const SCAN_WITHOUT_BALL_MAX = 1500;  // ticks at value=0

    public static function scanPeriodWithBall(float $value): int
    {
        $value = max(0.0, min(1.0, $value));
        return (int) round(self::SCAN_WITH_BALL_MAX - (self::SCAN_WITH_BALL_MAX - self::SCAN_WITH_BALL_MIN) * $value);
    }

    public static function scanPeriodWithoutBall(float $value): int
    {
        $value = max(0.0, min(1.0, $value));
        return (int) round(self::SCAN_WITHOUT_BALL_MAX - (self::SCAN_WITHOUT_BALL_MAX - self::SCAN_WITHOUT_BALL_MIN) * $value);
    }

    // -------------------------------------------------------------------------
    // MAX SPEED
    // -------------------------------------------------------------------------
    const MAX_SPEED_MIN = 1.0;  // units/tick at value=0
    const MAX_SPEED_MAX = 5.0;  // units/tick at value=1

    /** Convert a maxSpeed attribute (0–1) to base movement speed. */
    public static function maxSpeed(float $value): float
    {
        $value = max(0.0, min(1.0, $value));
        return self::MAX_SPEED_MIN + (self::MAX_SPEED_MAX - self::MAX_SPEED_MIN) * $value;
    }

    // -------------------------------------------------------------------------
    // ACCURACY
    // -------------------------------------------------------------------------
    const ACCURACY_DEVIATION_MIN = 0.0;   // pixels at value=1 (perfect accuracy)
    const ACCURACY_DEVIATION_MAX = 200.0;  // pixels at value=0 (very inaccurate)

    /** Convert an accuracy attribute (0–1) to random deviation in pixels for pass/shot targets. */
    public static function accuracyDeviation(float $value): float
    {
        $value = max(0.0, min(1.0, $value));
        return self::ACCURACY_DEVIATION_MAX - (self::ACCURACY_DEVIATION_MAX - self::ACCURACY_DEVIATION_MIN) * $value;
    }

    // -------------------------------------------------------------------------
    // CONTROL
    // -------------------------------------------------------------------------
    const CONTROL_SPEED_MIN = 1.0;  // ball speed threshold at value=0 (poor control)
    const CONTROL_SPEED_MAX = 9.0;  // ball speed threshold at value=1 (great control)

    /** Convert a control attribute (0–1) to the maximum ball speed at which the player can take possession. */
    public static function controlSpeedThreshold(float $value): float
    {
        $value = max(0.0, min(1.0, $value));
        return self::CONTROL_SPEED_MIN + (self::CONTROL_SPEED_MAX - self::CONTROL_SPEED_MIN) * $value;
    }

    // -------------------------------------------------------------------------
    // REACTION
    // -------------------------------------------------------------------------

    /**
     * Convert a reaction attribute (0–1) to a factor used in challenge resolution.
     * Higher values increase the chance to steal the ball.
     */
    public static function reactionFactor(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    // -------------------------------------------------------------------------
    // DRIBBLE
    // -------------------------------------------------------------------------

    /**
     * Convert a dribble attribute (0–1) to a factor used in challenge resolution.
     * Higher values reduce the chance of losing the ball.
     */
    public static function dribbleFactor(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    // -------------------------------------------------------------------------
    // STRENGTH  (resource depletion rate when running)
    // -------------------------------------------------------------------------
    const STRENGTH_DEPLETION_MIN = 0.0001;  // currentStrength lost per unit moved at value=1 (slow depletion)
    const STRENGTH_DEPLETION_MAX = 0.001;   // currentStrength lost per unit moved at value=0 (fast depletion)

    /** Convert a strength attribute (0–1) to the currentStrength depletion rate per unit of distance moved. */
    public static function strengthDepletionRate(float $value): float
    {
        $value = max(0.0, min(1.0, $value));
        return self::STRENGTH_DEPLETION_MAX - (self::STRENGTH_DEPLETION_MAX - self::STRENGTH_DEPLETION_MIN) * $value;
    }

    // -------------------------------------------------------------------------
    // ENDURANCE  (strength recovery rate per tick)
    // -------------------------------------------------------------------------
    const ENDURANCE_RECOVERY_MIN = 0.00005;  // currentStrength recovered per tick at value=0
    const ENDURANCE_RECOVERY_MAX = 0.0005;   // currentStrength recovered per tick at value=1

    /** Convert an endurance attribute (0–1) to the currentStrength recovery rate per tick. */
    public static function enduranceRecoveryRate(float $value): float
    {
        $value = max(0.0, min(1.0, $value));
        return self::ENDURANCE_RECOVERY_MIN + (self::ENDURANCE_RECOVERY_MAX - self::ENDURANCE_RECOVERY_MIN) * $value;
    }
}
