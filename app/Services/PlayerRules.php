<?php

namespace App\Services;

/**
 * Numeric identifiers for all player rule conditions and actions.
 * The frontend is responsible for mapping these IDs to human-readable labels.
 *
 * Conditions (C_*):
 *   1  → "I has the ball"
 *   2  → "I am marked"
 *   3  → "I am near a rival"
 *   4  → "The ball is near my goal"
 *   5  → "The ball is in my side"
 *   6  → "The ball is in other side"
 *   7  → "The ball is near rival goal"
 *   8  → "Rival in my side"
 *   9  → "No rival in my side"
 *
 * Actions (A_*):
 *   1  → "Stay in my zone"
 *   2  → "Go to the ball"
 *   3  → "Go to near rival"
 *   4  → "Go to my goal"
 *   5  → "Go to rival goal"
 *   6  → "Go forward"
 *   7  → "Go back"
 *   8  → "Pass the ball"
 *   9  → "Shoot to goal"
 *   10 → "Change side"
 */
class PlayerRules
{
    // Conditions
    const C_HAS_BALL            = 1;  // "I has the ball"
    const C_AM_MARKED           = 2;  // "I am marked"
    const C_NEAR_RIVAL          = 3;  // "I am near a rival"
    const C_BALL_NEAR_MY_GOAL   = 4;  // "The ball is near my goal"
    const C_BALL_IN_MY_SIDE     = 5;  // "The ball is in my side"
    const C_BALL_IN_OTHER_SIDE  = 6;  // "The ball is in other side"
    const C_BALL_NEAR_RIVAL_GOAL= 7;  // "The ball is near rival goal"
    const C_RIVAL_IN_MY_SIDE    = 8;  // "Rival in my side"
    const C_NO_RIVAL_IN_MY_SIDE = 9;  // "No rival in my side"

    // Actions
    const A_STAY_IN_ZONE     = 1;   // "Stay in my zone"
    const A_GO_TO_BALL       = 2;   // "Go to the ball"
    const A_GO_TO_NEAR_RIVAL = 3;   // "Go to near rival"
    const A_GO_TO_MY_GOAL    = 4;   // "Go to my goal"
    const A_GO_TO_RIVAL_GOAL = 5;   // "Go to rival goal"
    const A_GO_FORWARD       = 6;   // "Go forward"
    const A_GO_BACK          = 7;   // "Go back"
    const A_PASS             = 8;   // "Pass the ball"
    const A_SHOOT            = 9;   // "Shoot to goal"
    const A_CHANGE_SIDE      = 10;  // "Change side"
}
