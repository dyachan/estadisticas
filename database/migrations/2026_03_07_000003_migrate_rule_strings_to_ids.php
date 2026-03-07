<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts legacy string condition/action values in teams.configuration
 * to their numeric IDs (as defined in App\Services\PlayerRules).
 *
 * Conditions:
 *   "I has the ball"             → 1
 *   "I am marked"                → 2
 *   "I am near a rival"          → 3
 *   "The ball is near my goal"   → 4
 *   "The ball is in my side"     → 5
 *   "The ball is in other side"  → 6
 *   "The ball is near rival goal"→ 7
 *   "Rival in my side"           → 8
 *   "No rival in my side"        → 9
 *
 * Actions:
 *   "Stay in my zone"  → 1
 *   "Go to the ball"   → 2
 *   "Go to near rival" → 3
 *   "Go to my goal"    → 4
 *   "Go to rival goal" → 5
 *   "Go forward"       → 6
 *   "Go back"          → 7
 *   "Pass the ball"    → 8
 *   "Shoot to goal"    → 9
 *   "Change side"      → 10
 */
return new class extends Migration
{
    private const CONDITION_MAP = [
        'I has the ball'              => 1,
        'I am marked'                 => 2,
        'I am near a rival'           => 3,
        'The ball is near my goal'    => 4,
        'The ball is in my side'      => 5,
        'The ball is in other side'   => 6,
        'The ball is near rival goal' => 7,
        'Rival in my side'            => 8,
        'No rival in my side'         => 9,
    ];

    private const ACTION_MAP = [
        'Stay in my zone'  => 1,
        'Go to the ball'   => 2,
        'Go to near rival' => 3,
        'Go to my goal'    => 4,
        'Go to rival goal' => 5,
        'Go forward'       => 6,
        'Go back'          => 7,
        'Pass the ball'    => 8,
        'Shoot to goal'    => 9,
        'Change side'      => 10,
    ];

    public function up(): void
    {
        DB::table('teams')->whereNotNull('configuration')->orderBy('id')->each(function ($team) {
            $config = json_decode($team->configuration, true);
            if (!is_array($config)) return;

            $changed = false;
            foreach ($config as &$player) {
                foreach (['rules_with_ball', 'rules_without_ball'] as $slot) {
                    if (empty($player[$slot])) continue;
                    foreach ($player[$slot] as &$rule) {
                        if (isset($rule['condition']) && is_string($rule['condition'])) {
                            $rule['condition'] = self::CONDITION_MAP[$rule['condition']] ?? $rule['condition'];
                            $changed = true;
                        }
                        if (isset($rule['action']) && is_string($rule['action'])) {
                            $rule['action'] = self::ACTION_MAP[$rule['action']] ?? $rule['action'];
                            $changed = true;
                        }
                    }
                    unset($rule);
                }
            }
            unset($player);

            if ($changed) {
                DB::table('teams')->where('id', $team->id)->update([
                    'configuration' => json_encode($config),
                ]);
            }
        });
    }

    public function down(): void
    {
        $conditionFlip = array_flip(self::CONDITION_MAP);
        $actionFlip    = array_flip(self::ACTION_MAP);

        DB::table('teams')->whereNotNull('configuration')->orderBy('id')->each(function ($team) use ($conditionFlip, $actionFlip) {
            $config = json_decode($team->configuration, true);
            if (!is_array($config)) return;

            $changed = false;
            foreach ($config as &$player) {
                foreach (['rules_with_ball', 'rules_without_ball'] as $slot) {
                    if (empty($player[$slot])) continue;
                    foreach ($player[$slot] as &$rule) {
                        if (isset($rule['condition']) && is_int($rule['condition'])) {
                            $rule['condition'] = $conditionFlip[$rule['condition']] ?? $rule['condition'];
                            $changed = true;
                        }
                        if (isset($rule['action']) && is_int($rule['action'])) {
                            $rule['action'] = $actionFlip[$rule['action']] ?? $rule['action'];
                            $changed = true;
                        }
                    }
                    unset($rule);
                }
            }
            unset($player);

            if ($changed) {
                DB::table('teams')->where('id', $team->id)->update([
                    'configuration' => json_encode($config),
                ]);
            }
        });
    }
};
