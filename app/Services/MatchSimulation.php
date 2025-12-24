<?php

namespace App\Services;
use App\Services\Player;
use App\Services\MatchSummary;

class MatchSimulation
{
    private const BALLCOOLDOWN_PASS = 40;
    private const BALLCOOLDOWN_SHOOT = 50;
    private const BALLCOOLDOWN_FAILED_CONTROL = 50;
    private const BALLCOOLDOWN_BALL_DISPUTED = 40;
    private const BALLCOOLDOWN_TAKE_OFF = 40;
    private const BALLCOOLDOWN_FAIL_DEFENDING = 60;
    private const BALLCOOLDOWN_BALL_STEAL = 60;
    private const BODYCOOLDOWN_FAIL_DEFENDING = 70;
    private const BODYCOOLDOWN_BALL_STEAL = 70;
    private const BODYOVERLAP_FACTOR = 0.6;
    private const BODYMARKED_FACTOR = 1.5;
    private const BODYNEAR_FACTOR = 3;
    private const BODYASSIST_FACTOR = 2;

    private const PLAYER_SIZE = 38;
    private const GOAL_SIZE = 120;

    public float $width;
    public float $height;

    public array $players = [];

    public object $ball;
    public ?Player $currentPlayerWithBall = null;
    public ?Player $lastPlayerWithBall = null;

    public array $tickHistoric = [];
    public array $logs = []; // temporary for each tick

    public MatchSummary $summary;

    public function __construct($width = 400, $height = 600)
    {
        $this->width = $width;
        $this->height = $height;

        $this->ball = (object)[
            "x" => $width / 2,
            "y" => $height / 2,
            "vx" => 0,
            "vy" => 0
        ];

        $this->summary = new MatchSummary();
    }

    private function log($msg)
    {
        $this->logs[] = $msg;
    }

    // ------------------------------------------------------------------------------------
    // LOAD TEAMS
    // ------------------------------------------------------------------------------------
    public function loadTeams($teamAData, $teamBData)
    {
        $this->players = [];

        $initialPositions = [
            ["x" => $this->width * 0.5, "y" => $this->height * 0.25],
            ["x" => $this->width * 0.3, "y" => $this->height * 0.40],
            ["x" => $this->width * 0.7, "y" => $this->height * 0.40],
        ];

        // Team A
        foreach ($teamAData["players"] as $i => $p) {
            $this->players[] = new Player([
                "team" => "Team A",
                "name" => $p["name"],
                "rules" => $p["rules"],
                "x" => $initialPositions[$i]["x"],
                "y" => $initialPositions[$i]["y"],
                "baseX" => $this->width * $p["defaultZone"]["x"] / 100,
                "baseY" => $this->height * $p["defaultZone"]["y"] / 100,
                "defaultAction" => "Stay in my zone",
                "currentFieldSide" => "bottom"
            ]);
        }

        // Team B
        foreach ($teamBData["players"] as $i => $p) {
            $this->players[] = new Player([
                "team" => "Team B",
                "name" => $p["name"],
                "rules" => $p["rules"],
                "x" => $this->width - $initialPositions[$i]["x"],
                "y" => $this->height - $initialPositions[$i]["y"],
                "baseX" => $this->width * (100-$p["defaultZone"]["x"]) / 100,
                "baseY" => $this->height * (100-$p["defaultZone"]["y"]) / 100,
                "defaultAction" => "Stay in my zone",
                "currentFieldSide" => "top"
            ]);
        }
    }

    // ------------------------------------------------------------------------------------
    // MATCH UPDATE
    // ------------------------------------------------------------------------------------
    public function update(float $dt = 1)
    {
        // BALL MOVEMENT

        // owner keeps ball
        if ($this->currentPlayerWithBall) {
            $this->ball->x = $this->currentPlayerWithBall->x;
            $this->ball->y = $this->currentPlayerWithBall->y;
        } else {
            $this->ball->x += $this->ball->vx;
            $this->ball->y += $this->ball->vy;
            $this->ball->vx *= 0.98;
            $this->ball->vy *= 0.98;
        }

        $this->ball->x = max(10, min($this->width - 10, $this->ball->x));

        $ballOffset = self::PLAYER_SIZE * 0.5 + 3;

        // Check goal / reset
        if ($this->ball->y < $ballOffset || $this->ball->y > $this->height - $ballOffset) {
            if($this->ball->x > ($this->width - self::GOAL_SIZE) / 2 && 
                $this->ball->x < ($this->width + self::GOAL_SIZE) / 2){
                if($this->ball->y < $ballOffset){
                    $this->summary->goalsB++;
                    
                    if(!$this->lastPlayerWithBall){
                        $this->log("Team B get a goal");
                    } else {
                        if($this->lastPlayerWithBall->team == "Team B"){
                            $this->log("Team B {$this->lastPlayerWithBall->name} do a goal");
                            $this->lastPlayerWithBall->summary->goals++;
                        } else {
                            $this->log("Team B get an autogoal from {$this->lastPlayerWithBall->name}");
                        }
                    }
                } else {
                    $this->summary->goalsA++;

                    if(!$this->lastPlayerWithBall){
                        $this->log("Team A get a goal");
                    } else {
                        if($this->lastPlayerWithBall->team == "Team A"){
                            $this->log("Team A {$this->lastPlayerWithBall->name} do a goal");
                            $this->lastPlayerWithBall->summary->goals++;
                        } else {
                            $this->log("Team A get an autogoal from {$this->lastPlayerWithBall->name}");
                        }
                    }
                }
            } else {
                $this->log("reset ball");
            }
            $this->resetBall();
        }

        // get owner
        $this->currentPlayerWithBall = $this->findBallOwner();
        if($this->currentPlayerWithBall){
            if($this->currentPlayerWithBall->team === "Team A"){
                $this->summary->possessionA += $dt;
            } else {
                $this->summary->possessionB += $dt;
            }
        }

        // PLAYER LOGIC
        foreach ($this->players as $player) {
            $simState = [
                "ball" => $this->ball,
                "fieldWidth" => $this->width,
                "fieldHeight" => $this->height,
                "teammates" => array_values(array_filter($this->players, fn($p) => $p->team === $player->team && $p !== $player)),
                "opponents" => array_values(array_filter($this->players, fn($p) => $p->team !== $player->team && $p->bodyCooldown <= 0)),
                "ballTeam" => ($this->currentPlayerWithBall?->team ?? null),
                "ballChasers" => $this->selectBallChasers(),
                "assistDistance" => self::PLAYER_SIZE * self::BODYASSIST_FACTOR
            ];
            
            $player->checkMarked($simState["opponents"], self::PLAYER_SIZE * self::BODYMARKED_FACTOR);
            $player->checkOpponentNear($simState["opponents"], self::PLAYER_SIZE * self::BODYNEAR_FACTOR);

            if($player->marked){
                if($player->team === $simState['ballTeam']){
                    $player->summary->timeMarkedWithPossession += $dt;
                } else {
                    $player->summary->timeMarkedWithoutPossession += $dt;
                }
            }

            $action = $player->decide($simState);

            // EXECUTE ACTION
            $player->execute(
                $simState,
                function ($target) use ($player){
                    // passToCB
                    $this->lastPlayerWithBall = $player;
                    $dx = $target->x - $this->ball->x;
                    $dy = $target->y - $this->ball->y;
                    $dist = sqrt($dx*$dx + $dy*$dy);
                    $this->applyForceToBall(['x' => $target->x, 'y' => $target->y], 2 + min($dist*0.01, 4));
                    $player->ballCooldown = self::BALLCOOLDOWN_PASS;
                    $this->log("{$player->team} {$player->name} pass the ball to {$target->name}");
                },
                function ($team) use ($player) {
                    $this->lastPlayerWithBall = $player;
                    // shootToCB
                    $target = [
                        "x" => (($this->width + self::GOAL_SIZE) * 0.5) - rand(0, self::GOAL_SIZE),
                        "y" => ($team === "Team A" ? $this->height : 0)
                    ];
                    $this->applyForceToBall($target, 10);
                    $player->ballCooldown = self::BALLCOOLDOWN_SHOOT;
                    $this->log("{$player->team} {$player->name} shoot to goal");
                }
            );

            $player->update($dt);

            // PLAYER COLLISIONS AND DISTANCE
            foreach ($this->players as $other) {
                if ($other === $player || $other->bodyCooldown > 0) continue;

                $dx = $player->x - $other->x;
                $dy = $player->y - $other->y;
                $dist = sqrt($dx*$dx + $dy*$dy);

                if ($dist < self::PLAYER_SIZE * self::BODYOVERLAP_FACTOR && $dist > 0) {
                    $overlap = self::PLAYER_SIZE * self::BODYOVERLAP_FACTOR - $dist;
                    $player->x += ($dx / $dist) * ($overlap / 2);
                    $player->y += ($dy / $dist) * ($overlap / 2);

                    $other->x -= ($dx / $dist) * ($overlap / 2);
                    $other->y -= ($dy / $dist) * ($overlap / 2);
                }
            }
        }

        // BALL POSESSION LOGIC
        $this->handleBallPossession();

        $this->tickHistoric[] = [
            "ball" => [
                "x" => $this->ball->x,
                "y" => $this->ball->y,
            ],
            "teamA" => [
                "goalkeeper" => $this->players[0]->getRenderData(),
                "defender" => $this->players[1]->getRenderData(),
                "striker" => $this->players[2]->getRenderData(),
            ],
            "teamB" => [
                "goalkeeper" => $this->players[3]->getRenderData(),
                "defender" => $this->players[4]->getRenderData(),
                "striker" => $this->players[5]->getRenderData(),
            ],
            "logs" => $this->logs
        ];
        $this->logs = [];

        $this->summary->totalTime += $dt;
    }

    private function resetBall()
    {
        $this->ball->x = $this->width * 0.5;
        $this->ball->y = $this->height * 0.5;
        $this->ball->vx = 0;
        $this->ball->vy = 0;

        foreach ($this->players as $p) {
            $p->hasBall = false;
        }
        $this->currentPlayerWithBall = null;
        $this->lastPlayerWithBall = null;
    }

    // ------------------------------------------------------------------------------------
    // BALL FORCE
    // ------------------------------------------------------------------------------------
    private function applyForceToBall($target, float $force)
    {
        $dx = $target["x"] - $this->ball->x;
        $dy = $target["y"] - $this->ball->y;
        $dist = sqrt($dx*$dx + $dy*$dy);

        if ($dist == 0) return;

        $this->ball->vx = ($dx / $dist) * $force;
        $this->ball->vy = ($dy / $dist) * $force;

        $this->currentPlayerWithBall = null;
    }

    private function findBallOwner()
    {
        foreach ($this->players as $p) {
            if ($p->hasBall) return $p;
        }
        return null;
    }

    // ------------------------------------------------------------------------------------
    // BALL POSSESSION LOGIC
    // ------------------------------------------------------------------------------------
    private function handleBallPossession()
    {
        $CONTROL_DISTANCE = self::PLAYER_SIZE;

        $nearPlayers = array_filter($this->players, function($p) use ($CONTROL_DISTANCE) {
            $dx = $p->x - $this->ball->x;
            $dy = $p->y - $this->ball->y;
            $dist = hypot($dx, $dy);
            return $dist < $CONTROL_DISTANCE && $p->ballCooldown <= 0;
        });

        $nearPlayers = array_values($nearPlayers);


        if ($this->currentPlayerWithBall) {
            // challenges, steals, bounces
            $this->resolveOwnerChallenge($nearPlayers);
            return;
        }

        // NO OWNER: decide new owner or bounce
        if (count($nearPlayers) === 0) return;

        $sameTeam = array_reduce($nearPlayers, fn($c,$p)=> $c && $p->team === $nearPlayers[0]->team, true);

        if ($sameTeam) { // all near players are in same team
            $newOwner = $nearPlayers[array_rand($nearPlayers)];

            $ballSpeed = hypot($this->ball->vx, $this->ball->vy);

            if ($ballSpeed > 6.0) {
                $angle = rand(0, 10000) / 10000 * 2 * M_PI;
                $reb = $ballSpeed * 0.3;
                $this->ball->vx = cos($angle) * $reb;
                $this->ball->vy = sin($angle) * $reb;

                $newOwner->ballCooldown = self::BALLCOOLDOWN_FAILED_CONTROL;
                $this->log("{$newOwner->team} {$newOwner->name} failed to control fast ball");
                if($this->lastPlayerWithBall && $this->lastPlayerWithBall->team != $newOwner->team){
                    $this->lastPlayerWithBall = null;
                }

                $newOwner->summary->interceptedBalls++;

            } else {
                $newOwner->hasBall = true;
                $this->ball->x = $newOwner->x;
                $this->ball->y = $newOwner->y;
                $this->ball->vx = 0;
                $this->ball->vy = 0;

                $this->log("{$newOwner->team} {$newOwner->name} take ball");

                if($this->lastPlayerWithBall && $this->lastPlayerWithBall->team == $newOwner->team){
                    $this->lastPlayerWithBall->summary->passesAchieved++;
                }
                $newOwner->summary->controledBalls++;

                $this->currentPlayerWithBall = $newOwner;
                $this->lastPlayerWithBall = null;
            }

        } else {
            foreach ($nearPlayers as $p){
                $p->ballCooldown = self::BALLCOOLDOWN_BALL_DISPUTED;
            }
            $this->lastPlayerWithBall = null;

            $this->applyForceToBall([
                "x" => $this->ball->x + (rand(-100,100)),
                "y" => $this->ball->y + (rand(-100,100))
            ], 3);

            $this->log("ball bounces away");
        }
    }

    private function resolveOwnerChallenge($nearPlayers)
    {
        $opponents = array_filter($nearPlayers, fn($p)=> $p->team !== $this->currentPlayerWithBall->team);

        foreach ($opponents as $op) {
            $chance = rand(0, 1000) / 1000;
            $op->summary->challengedRivalWithBall++;
            $this->currentPlayerWithBall->summary->challengedMeWithBall++;

            if ($chance < 0.2) { // steal ball 
                $op->summary->stealedBalls++;
                $this->currentPlayerWithBall->summary->dribbleFailed++;

                $this->currentPlayerWithBall->hasBall = false;
                $this->currentPlayerWithBall->ballCooldown = self::BALLCOOLDOWN_BALL_STEAL;
                $this->currentPlayerWithBall->bodyCooldown = self::BODYCOOLDOWN_FAIL_DEFENDING;

                $op->hasBall = true;
                $this->ball->x = $op->x;
                $this->ball->y = $op->y;
                $this->ball->vx = 0;
                $this->ball->vy = 0;

                $this->log("{$op->team} {$op->name} steal ball to {$this->currentPlayerWithBall->name}");

                $this->currentPlayerWithBall = $op;
                $this->lastPlayerWithBall = null;

                return;

            } elseif ($chance < 0.5) {
                $op->summary->takedoffBalls++;
                $this->currentPlayerWithBall->summary->dribbleFailed++;

                $this->currentPlayerWithBall->hasBall = false;
                $this->currentPlayerWithBall->ballCooldown = self::BALLCOOLDOWN_TAKE_OFF;
                $op->ballCooldown = self::BALLCOOLDOWN_TAKE_OFF;

                $this->log("{$op->team} {$op->name} take off ball to {$this->currentPlayerWithBall->name}");

                $this->applyForceToBall([
                    "x" => $this->ball->x + rand(-100,100)*2,
                    "y" => $this->ball->y + rand(-100,100)*2
                ], 4);    

                $this->lastPlayerWithBall = null;

                return;

            } else {
                $this->currentPlayerWithBall->summary->dribbleDone++;

                $op->ballCooldown = self::BALLCOOLDOWN_FAIL_DEFENDING;
                $op->bodyCooldown = self::BODYCOOLDOWN_BALL_STEAL;
                $this->log("{$op->team} {$op->name} fails defending {$this->currentPlayerWithBall->name}");
            }
        }
    }

    private function selectBallChasers(): array
    {
        $chasers = [];

        foreach (['Team A', 'Team B'] as $team) {
            $candidates = array_filter($this->players, fn($p) =>
                $p->team === $team && $p->bodyCooldown <= 0 &&
                in_array($p->currentAction, ["Go to the ball", "Go to near rival"])
            );

            if (empty($candidates)) {
                $chasers[$team] = null;
                continue;
            }

            usort($candidates, fn($a, $b) =>
                hypot($a->x - $this->ball->x, $a->y - $this->ball->y)
                <=>
                hypot($b->x - $this->ball->x, $b->y - $this->ball->y)
            );

            $chasers[$team] = $candidates[0]; // el más cercano
        }

        return $chasers;
    }

    public function getSummary(){
        $teamA = [];
        for($i = 0; $i < 3; $i++){
            $teamA[] = $this->players[$i]->getSummary($this->summary->possessionA, $this->summary->totalTime - $this->summary->possessionA);
        }
        $teamB = [];
        for($i = 3; $i < 6; $i++){
            $teamB[] = $this->players[$i]->getSummary($this->summary->possessionB, $this->summary->totalTime - $this->summary->possessionB);
        }

        return [
            "GoalsA" => $this->summary->goalsA,
            "GoalsB" => $this->summary->goalsB,
            "totalTime" => $this->summary->totalTime,
            "possessionA" => $this->summary->possessionA,
            "possessionB" => $this->summary->possessionB,
            "TeamA" => $teamA,
            "TeamB" => $teamB
        ];
    }
}