<?php

namespace App\Services;

use App\Services\PlayerSummary;
use App\Services\PlayerFormulas;

/**
 * All players have a memory of where other players and the ball are.
 * Near players are always updated; away players are scanned periodically.
 *
 * Player stats (0.0–1.0 normalized, passed via config):
 * - scanWithBall / scanWithoutBall : how often memory is refreshed
 * - strength    : depletion rate when running; affects effective max speed,
 *                 pass/shoot force, and resistance to ball loss in challenges
 * - endurance   : currentStrength recovery rate per tick
 * - maxSpeed    : base maximum movement speed
 * - accuracy    : pass and shot targeting precision (higher = less deviation)
 * - control     : ball speed threshold for successful ball control
 * - reaction    : chance to steal the ball during a challenge (as defender)
 * - dribble     : resistance to ball loss during a challenge (as attacker)
 */
class Player
{
    public string $team;
    public string $name;
    public array $rules;
    public float $x;
    public float $y;
    public float $baseX;
    public float $baseY;

    public bool $hasBall = false;
    public bool $marked = false;
    public bool $opponentNear = false;

    public string $defaultAction;
    public string $currentFieldSide;

    public float $ballCooldown = 0;
    public float $bodyCooldown = 0;

    public float $maxSpeed = 2.0;
    public float $accuracyDeviation = 40.0;      // pixels of random deviation on pass/shot targets
    public float $controlSpeedThreshold = 6.0;   // max ball speed at which this player can take possession
    public float $reactionFactor = 0.5;           // defender steal factor (0–1)
    public float $dribbleFactor = 0.5;            // attacker keep-ball factor (0–1)
    public float $strengthDepletionRate = 0.0005; // currentStrength lost per unit of distance moved
    public float $enduranceRecoveryRate = 0.00025;// currentStrength recovered per tick
    public float $currentStrength = 1.0;          // runtime resource (0–1); depletes when running

    public array $currentSpeed = ['vx' => 0, 'vy' => 0];
    public ?array $target = null;

    public string $currentAction;
    public ?string $currentCondition = null;

    public PlayerSummary $summary;
    // Memory for simulated context
    public array $memory = [
        'ball' => null,
        'teammates' => [],
        'opponents' => [],
        'fieldWidth' => null,
        'fieldHeight' => null,
        'ballTeam' => null,
        'ballChasers' => [],
        'assistDistance' => null,
    ];

    // Tick when memory was last refreshed
    public int $memoryLastTick = -1;

    // How many ticks to wait before refreshing memory depending on ball possession
    public int $memoryRefreshPeriodWithBall = 300;
    public int $memoryRefreshPeriodWithoutBall = 300;

    public function __construct(array $config)
    {
        $this->team = $config['team'];
        $this->name = $config['name'];
        $this->rules = $config['rules'] ?? [[], []];
        $this->x = $config['x'];
        $this->y = $config['y'];
        $this->baseX = $config['baseX'];
        $this->baseY = $config['baseY'];

        $this->defaultAction = $config['defaultAction'];
        $this->currentFieldSide = $config['currentFieldSide'];

        $this->target = ['x' => $this->baseX, 'y' => $this->baseY];
        $this->currentAction = $this->defaultAction;

        $this->summary = new PlayerSummary();
        if (isset($config['scanWithBall'])) {
            $this->memoryRefreshPeriodWithBall = PlayerFormulas::scanPeriodWithBall($config['scanWithBall']);
        }
        if (isset($config['scanWithoutBall'])) {
            $this->memoryRefreshPeriodWithoutBall = PlayerFormulas::scanPeriodWithoutBall($config['scanWithoutBall']);
        }
        if (isset($config['maxSpeed'])) {
            $this->maxSpeed = PlayerFormulas::maxSpeed($config['maxSpeed']);
        }
        if (isset($config['accuracy'])) {
            $this->accuracyDeviation = PlayerFormulas::accuracyDeviation($config['accuracy']);
        }
        if (isset($config['control'])) {
            $this->controlSpeedThreshold = PlayerFormulas::controlSpeedThreshold($config['control']);
        }
        if (isset($config['reaction'])) {
            $this->reactionFactor = PlayerFormulas::reactionFactor($config['reaction']);
        }
        if (isset($config['dribble'])) {
            $this->dribbleFactor = PlayerFormulas::dribbleFactor($config['dribble']);
        }
        if (isset($config['strength'])) {
            $this->strengthDepletionRate = PlayerFormulas::strengthDepletionRate($config['strength']);
        }
        if (isset($config['endurance'])) {
            $this->enduranceRecoveryRate = PlayerFormulas::enduranceRecoveryRate($config['endurance']);
        }
    }

    /** Update cached memory from a fresh simState and record tick */
    public function refreshMemory(array $simState, int $tick): void
    {
        $this->memory['ball'] = $simState['ball'];
        $this->memory['fieldWidth'] = $simState['fieldWidth'];
        $this->memory['fieldHeight'] = $simState['fieldHeight'];
        // store shallow copies of teammates/opponents (positions and flags)
        $this->memory['teammates'] = array_map(fn($p) => (object)['x' => $p->x, 'y' => $p->y, 'name' => $p->name, 'marked' => $p->marked], $simState['teammates']);
        $this->memory['opponents'] = array_map(fn($p) => (object)['x' => $p->x, 'y' => $p->y, 'name' => $p->name, 'marked' => $p->marked], $simState['opponents']);
        $this->memory['ballTeam'] = $simState['ballTeam'];
        $this->memory['ballChasers'] = $simState['ballChasers'];
        $this->memory['assistDistance'] = $simState['assistDistance'];
        $this->memoryLastTick = $tick;
    }

    public function getRenderData(){
        return [
            'x' => $this->x, 'y' => $this->y,
            'condition' => $this->currentCondition,
            'ballCooldown' => $this->ballCooldown,
            'bodyCooldown' => $this->bodyCooldown,
            'marked' => $this->marked,
            'currentStrength' => $this->currentStrength,
        ];
    }

    public function getSummary($teamWithBallTime, $teamWithoutBallTime){
        return [
            "name" => $this->name,
            "distanceTraveled" => $this->summary->distanceTraveled,
            "distanceTraveledWithBall" => $this->summary->distanceTraveledWithBall,
            "timeMarkedWithPossession" => $teamWithBallTime > 0 ? 
                100 * $this->summary->timeMarkedWithPossession / $teamWithBallTime : 0,
            "timeMarkedWithoutPossession" => $teamWithoutBallTime > 0 ? 
                100 * $this->summary->timeMarkedWithoutPossession / $teamWithoutBallTime : 0,
            "controledBalls" => $this->summary->controledBalls,
            "interceptedBalls" => $this->summary->interceptedBalls,
            "passesMade" => $this->summary->passesMade,
            "passesAchieved" => $this->summary->passesAchieved,
            "shootMade" => $this->summary->shootMade,
            "goals" => $this->summary->goals,
            "takedoffBalls" => $this->summary->takedoffBalls,
            "stealedBalls" => $this->summary->stealedBalls,
            "challengedMeWithBall" => $this->summary->challengedMeWithBall,
            "challengedRivalWithBall" => $this->summary->challengedRivalWithBall,
            "takedoffBalls" => $this->summary->takedoffBalls,
            "stealedBalls" => $this->summary->stealedBalls,
            "dribbleFailed" => $this->summary->dribbleFailed,
            "dribbleDone" => $this->summary->dribbleDone,
        ];
    }

    /** Reset position */
    public function resetPosition(): void
    {
        $this->x = $this->baseX;
        $this->y = $this->baseY;
    }

    public function update(float $dt): void
    {
        if ($this->ballCooldown > 0) {
            $this->ballCooldown -= $dt;
        }

        if ($this->bodyCooldown > 0) {
            $this->bodyCooldown -= $dt;
        }

        // Recover strength over time (endurance determines recovery speed)
        $this->currentStrength = min(1.0, $this->currentStrength + $this->enduranceRecoveryRate * $dt);

        $this->moveToward($dt);
    }

    /** Calculate hypot distance */
    public function distanceTo($obj): float
    {
        return hypot($obj->x - $this->x, $obj->y - $this->y);
    }

    /** Sign replacement */
    private function sign(float $value): int
    {
        if ($value > 0) return 1;
        if ($value < 0) return -1;
        return 0;
    }

    /** Movement with inertia */
    public function moveToward(float $dt = 1): void
    {
        if ($this->target === null || $this->bodyCooldown > 0) return;

        $acceleration = 0.2;
        $deceleration = 0.3;
        $stopThreshold = 0.5;

        $dx = $this->target['x'] - $this->x;
        $dy = $this->target['y'] - $this->y;
        $dist = hypot($dx, $dy);

        // Stop if close
        if ($dist < $stopThreshold) {
            $this->currentSpeed['vx'] *= 0.8;
            $this->currentSpeed['vy'] *= 0.8;

            if (abs($this->currentSpeed['vx']) < 0.05) $this->currentSpeed['vx'] = 0;
            if (abs($this->currentSpeed['vy']) < 0.05) $this->currentSpeed['vy'] = 0;

            if ($this->currentSpeed['vx'] === 0 && $this->currentSpeed['vy'] === 0) {
                $this->target = null;
            }
            return;
        }

        // Normalize
        $dirX = $dx / $dist;
        $dirY = $dy / $dist;

        // Strength reduces effective max speed (40% floor when fully exhausted)
        $effectiveMaxSpeed = $this->maxSpeed * (0.4 + 0.6 * $this->currentStrength);

        // ----- X axis -----
        $desiredVx = $dirX * $effectiveMaxSpeed;
        $changingDirX = $this->sign($this->currentSpeed['vx']) !== $this->sign($desiredVx)
                        && abs($this->currentSpeed['vx']) > 0.1;

        if ($changingDirX) {
            $this->currentSpeed['vx'] -= $this->sign($this->currentSpeed['vx']) * $deceleration * $dt;
        } else {
            if (abs($this->currentSpeed['vx'] - $desiredVx) > 0.05) {
                $this->currentSpeed['vx'] += $this->sign($desiredVx - $this->currentSpeed['vx']) * $acceleration * $dt;
            }
        }

        // ----- Y axis -----
        $desiredVy = $dirY * $effectiveMaxSpeed;
        $changingDirY = $this->sign($this->currentSpeed['vy']) !== $this->sign($desiredVy)
                        && abs($this->currentSpeed['vy']) > 0.1;

        if ($changingDirY) {
            $this->currentSpeed['vy'] -= $this->sign($this->currentSpeed['vy']) * $deceleration * $dt;
        } else {
            if (abs($this->currentSpeed['vy'] - $desiredVy) > 0.05) {
                $this->currentSpeed['vy'] += $this->sign($desiredVy - $this->currentSpeed['vy']) * $acceleration * $dt;
            }
        }

        // ========= APPLY MOVEMENT =========
        $moveX = $this->currentSpeed['vx'] * $dt;
        $moveY = $this->currentSpeed['vy'] * $dt;

        // Track distance traveled and deplete strength accordingly
        $movedDist = hypot($moveX, $moveY);
        $this->summary->distanceTraveled += $movedDist;
        if ($this->hasBall) {
            $this->summary->distanceTraveledWithBall += $movedDist;
        }
        $this->currentStrength = max(0.0, $this->currentStrength - $this->strengthDepletionRate * $movedDist);

        $this->x += $moveX;
        $this->y += $moveY;
    }

    /** Update marked status */
    public function checkMarked(array $opponents, float $markRadius): bool
    {
        $this->marked = false;

        foreach ($opponents as $op) {
            if ($this->distanceTo($op) < $markRadius) {
                $this->marked = true;
                break;
            }
        }
        return $this->marked;
    }

    public function checkOpponentNear(array $opponents, float $markRadius): bool
    {
        $this->opponentNear = false;

        foreach ($opponents as $op) {
            if ($this->distanceTo($op) < $markRadius) {
                $this->opponentNear = true;
                break;
            }
        }
        return $this->opponentNear;
    }

    /** Evaluate rules */
    public function decide(array $simState)
    {
        // If a tick is provided, let the player decide when to refresh its memory
        $tick = $simState['tick'] ?? null;

        // ballChasers and assistDistance are used for assist behavior, so we update them every tick to ensure good responsiveness
        $this->memory['ballChasers'] = $simState['ballChasers'];
        $this->memory['assistDistance'] = $simState['assistDistance'];
        if ($tick !== null) {
            $period = $this->hasBall ? $this->memoryRefreshPeriodWithBall : $this->memoryRefreshPeriodWithoutBall;
            $shouldRefresh = ($this->memoryLastTick < 0) || ($tick - $this->memoryLastTick >= (0.5+rand(0, 10000) / 20000)*$period);
            if ($shouldRefresh) {
                $this->refreshMemory($simState, $tick);
            } else {
                // Use cached memory but update near ball and players
                if($this->distanceTo($simState['ball']) < $simState['assistDistance']){
                    $this->memory['ball'] = $simState['ball'];
                }
                // Update near teammates and opponents
                foreach ($this->memory['teammates'] as $cachedMate) {
                    $realMate = array_values(array_filter($simState['teammates'], fn($p) => $p->name === $cachedMate->name))[0] ?? null;
                    if ($realMate && $this->distanceTo($realMate) < $simState['assistDistance']) {
                        $cachedMate->x = $realMate->x;
                        $cachedMate->y = $realMate->y;
                        $cachedMate->marked = $realMate->marked;
                    }
                }
                foreach ($this->memory['opponents'] as $cachedOpp) {
                    $realOpp = array_values(array_filter($simState['opponents'], fn($p) => $p->name === $cachedOpp->name))[0] ?? null;
                    if ($realOpp && $this->distanceTo($realOpp) < $simState['assistDistance']) {
                        $cachedOpp->x = $realOpp->x;
                        $cachedOpp->y = $realOpp->y;
                        $cachedOpp->marked = $realOpp->marked;
                    }
                }
            }
        }

        $rulesForContext = $this->rules[$this->memory['ballTeam'] === $this->team ? 0 : 1];

        foreach ($rulesForContext as $rule) {
            if ($this->evaluateCondition($rule['condition'], $rule['action'], $this->memory)) {
                $this->currentCondition = $rule['condition'];
                $this->currentAction = $rule['action'];
                return $rule['action'];
            }
        }

        $this->currentCondition = null;
        $this->currentAction = $this->defaultAction;
        return $this->currentAction;
    }

    /** Execute action */
    public function execute(callable $passToCB, callable $shootToCB)
    {
        $ball = $this->memory['ball'];
        $teammates = $this->memory['teammates'];
        $opponents = $this->memory['opponents'];
        $fieldWidth = $this->memory['fieldWidth'];
        $fieldHeight = $this->memory['fieldHeight'];

        switch ($this->currentAction) {

            case "Go to the ball":
                $this->setTarget(['x' => $ball->x, 'y' => $ball->y], $this->memory);
                break;

            case "Go to near rival":
                if (count($opponents) > 0) {
                    $closest = null;
                    $closestDist = INF;

                    foreach ($opponents as $opp) {
                        $d2 = ($opp->x - $this->x) ** 2 + ($opp->y - $this->y) ** 2;
                        if ($d2 < $closestDist) {
                            $closestDist = $d2;
                            $closest = $opp;
                        }
                    }

                    if ($closest) {
                        $this->setTarget(['x' => $closest->x, 'y' => $closest->y], $this->memory);
                    }
                }
                break;

            case "Go to my goal":
                $goalY = $this->currentFieldSide === "bottom" ? 10 : $fieldHeight - 10;
                $this->setTarget(['x' => $fieldWidth / 2, 'y' => $goalY], $this->memory);
                break;

            case "Go to rival goal":
                $goalY = $this->currentFieldSide === "top" ? 10 : $fieldHeight - 10;
                $this->setTarget(['x' => $fieldWidth / 2, 'y' => $goalY], $this->memory);
                break;

            case "Go forward":
                $goalY = $this->currentFieldSide === "top" ? 10 : $fieldHeight - 10;
                $this->setTarget(['x' => $this->x, 'y' => $goalY], $this->memory);
                break;

            case "Go back":
                $goalY = $this->currentFieldSide === "bottom" ? 10 : $fieldHeight - 10;
                $this->setTarget(['x' => $this->x, 'y' => $goalY], $this->memory);
                break;

            case "Pass the ball":
                if ($this->hasBall) {
                    // Filter free teammates
                    $free = array_filter($teammates, fn($p) => !$p->marked);

                    if (count($free) > 0) {
                        $available = [];

                        foreach ($free as $mate) {
                            $blocked = false;

                            foreach ($opponents as $op) {
                                if ($this->isPassBlocked($mate, $op)) {
                                    $blocked = true;
                                    break;
                                }
                            }

                            if (!$blocked) $available[] = $mate;
                        }

                        if (count($available) > 0) {
                            $target = $available[array_rand($available)];
                            $this->hasBall = false;
                            $this->summary->passesMade++;
                            $passToCB($target);
                        }
                    }
                }
                break;

            case "Shoot to goal":
                if ($this->hasBall) {
                    $this->hasBall = false;
                    $this->summary->shootMade++;
                    $shootToCB($this->team);
                }
                break;

            case "Change side":
                $left = ['x' => $fieldWidth * (0.3-rand(1,20)/100), 'y' => $this->y];
                $right = ['x' => $fieldWidth * (0.7+rand(1,20)/100), 'y' => $this->y];

                if ($this->x >= $right['x']) {
                    $this->setTarget($left, $this->memory);
                } else if($this->x <= $left['x']) {
                    $this->setTarget($right, $this->memory);
                } else if($this->target == null){
                    if($this->x < $fieldWidth*0.5){
                        $this->setTarget($right, $this->memory);
                    } else {
                        $this->setTarget($left, $this->memory);
                    }
                }
                break;

            default:
                $this->setTarget(['x' => $this->baseX, 'y' => $this->baseY], $this->memory);
                break;
        }
    }

    /** Check pass interception */
    private function isPassBlocked($mate, $opponent): bool
    {
        $ax = $this->x;
        $ay = $this->y;
        $bx = $mate->x;
        $by = $mate->y;
        $px = $opponent->x;
        $py = $opponent->y;

        $abx = $bx - $ax;
        $aby = $by - $ay;
        $apx = $px - $ax;
        $apy = $py - $ay;

        $abLenSq = $abx * $abx + $aby * $aby;

        if ($abLenSq == 0) return true;

        $t = max(0, min(1, ($apx * $abx + $apy * $aby) / $abLenSq));

        $closestX = $ax + $abx * $t;
        $closestY = $ay + $aby * $t;

        $dist = hypot($px - $closestX, $py - $closestY);
        $interceptionThreshold = 25;

        return $dist < $interceptionThreshold && $t > 0.05 && $t < 0.95;
    }

    /** Evaluate conditions */
    public function evaluateCondition(string $cond, string $action, array $simState): bool
    {
        $ball = $simState['ball'];
        $opponents = $simState['opponents'];
        $fieldWidth = $simState['fieldWidth'];
        $fieldHeight = $simState['fieldHeight'];

        if (in_array($action, ["Pass the ball", "Shoot to goal"]) && !$this->hasBall) {
            return false;
        }

        if ($action === "Go to near rival" && !$this->opponentNear) {
            return false;
        }

        switch ($cond) {
            case "I has the ball":
                return $this->hasBall;

            case "I am marked":
                return $this->marked;

            case "I am near a rival":
                return $this->opponentNear;

            case "The ball is near my goal":
                return ($this->currentFieldSide === "bottom" && $ball->y < $fieldHeight * 0.3) ||
                       ($this->currentFieldSide === "top" && $ball->y > $fieldHeight * 0.7);

            case "The ball is in my side":
                return ($this->currentFieldSide === "bottom" && $ball->y < $fieldHeight * 0.51) ||
                       ($this->currentFieldSide === "top" && $ball->y > $fieldHeight * 0.49);

            case "The ball is in other side":
                return ($this->currentFieldSide === "top" && $ball->y < $fieldHeight * 0.51) ||
                       ($this->currentFieldSide === "bottom" && $ball->y > $fieldHeight * 0.49);

            case "The ball is near rival goal":
                return ($this->currentFieldSide === "top" && $ball->y < $fieldHeight * 0.3) ||
                       ($this->currentFieldSide === "bottom" && $ball->y > $fieldHeight * 0.7);

            case "Rival in my side":
                return ($this->currentFieldSide === "top" && !collect($opponents)->every(fn($p) => $p->y > $fieldHeight * 0.49))
                    || ($this->currentFieldSide === "bottom" && !collect($opponents)->every(fn($p) => $p->y < $fieldHeight * 0.51));

            case "No rival in my side":
                return ($this->currentFieldSide === "top" && collect($opponents)->every(fn($p) => $p->y > $fieldHeight * 0.49))
                    || ($this->currentFieldSide === "bottom" && collect($opponents)->every(fn($p) => $p->y < $fieldHeight * 0.51));

            default:
                return false;
        }
    }

    public function setTarget($target, $simState){
        if(!$simState["ballChasers"][$this->team] || $simState["ballChasers"][$this->team] === $this){
            $this->target = $target;
        } else {
            $chasser = $simState["ballChasers"][$this->team];
            // target teammate distance
            $dx = $target['x'] - $chasser->x;
            $dy = $target['y'] - $chasser->y;
            $targetTeammateDist = hypot($dx, $dy);

            if($targetTeammateDist < $simState["assistDistance"]){ // assist player from distance
                // chasser player vector
                $vx = $this->x - $chasser->x;
                $vy = $this->y - $chasser->y;
                $len = hypot($vx, $vy);
                if ($len === 0) {
                    $vx = 1;
                    $vy = 0;
                    $len = 1;
                }

                // Normalizar y escalar
                $vx /= $len;
                $vy /= $len;

                $this->target = [
                    'x' => min($simState["fieldWidth"], max(0, $chasser->x + $vx * $simState["assistDistance"])),
                    'y' => min($simState["fieldHeight"], max(0, $chasser->y + $vy * $simState["assistDistance"]))
                ];
            }
        }
    }
}