<?php

namespace App\Services;
use App\Services\Player;

class MatchSimulation
{
    private const BALLCOOLDOWN_PASS = 20;
    private const BALLCOOLDOWN_SHOOT = 30;
    private const BALLCOOLDOWN_FAILED_CONTROL = 30;
    private const BALLCOOLDOWN_BALL_DISPUTED = 20;
    private const BALLCOOLDOWN_BALL_STEAL = 60;
    private const BALLCOOLDOWN_TAKE_OFF = 20;
    private const BALLCOOLDOWN_FAIL_DEFENDING = 60;
    private const BODYCOOLDOWN_FAIL_DEFENDING = 60;
    private const BODYCOOLDOWN_BALL_STEAL = 60;

    private float $PLAYER_SIZE = 38;
    private float $GOAL_SIZE = 120;

    public float $width;
    public float $height;

    public array $players = [];

    public object $ball;

    public array $tickHistoric = [];
    public array $logs = [];
    public string $ballOwnerTeam = "";

    public function __construct($width = 800, $height = 400)
    {
        $this->width = $width;
        $this->height = $height;

        $this->ball = (object)[
            "x" => $width / 2,
            "y" => $height / 2,
            "vx" => 0,
            "vy" => 0
        ];
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
            ["x" => $this->width * 0.25, "y" => $this->height * 0.5],
            ["x" => $this->width * 0.40, "y" => $this->height * 0.3],
            ["x" => $this->width * 0.40, "y" => $this->height * 0.7],
        ];

        // Team A
        foreach ($teamAData["players"] as $i => $p) {
            $this->players[] = new Player([
                "team" => "Team A",
                "name" => $p["name"],
                "rules" => $p["rules"],
                "x" => $initialPositions[$i]["x"],
                "y" => $initialPositions[$i]["y"],
                "baseX" => $this->width * ($p["defaultZone"]["x"] / 100),
                "baseY" => $this->height * ($p["defaultZone"]["y"] / 100),
                "defaultAction" => "Keep in my zone",
                "currentFieldSide" => "left"
            ]);
        }

        // Team B
        foreach ($teamBData["players"] as $i => $p) {
            $this->players[] = new Player([
                "team" => "Team B",
                "name" => $p["name"],
                "rules" => $p["rules"],
                "x" => $this->width - $initialPositions[$i]["x"],
                "y" => $initialPositions[$i]["y"],
                "baseX" => $this->width * ($p["defaultZone"]["x"] / 100),
                "baseY" => $this->height * ($p["defaultZone"]["y"] / 100),
                "defaultAction" => "Keep in my zone",
                "currentFieldSide" => "right"
            ]);
        }
    }

    // ------------------------------------------------------------------------------------
    // MATCH UPDATE
    // ------------------------------------------------------------------------------------
    public function update(float $dt = 1)
    {
        // BALL MOVEMENT
        $this->ball->x += $this->ball->vx;
        $this->ball->y += $this->ball->vy;
        $this->ball->vx *= 0.98;
        $this->ball->vy *= 0.98;

        $this->ball->y = max(10, min($this->height - 10, $this->ball->y));

        $ballOffset = $this->PLAYER_SIZE * 0.5 + 3;

        // Check goal / reset
        if ($this->ball->x < $ballOffset || $this->ball->x > $this->width - $ballOffset) {
            if($this->ball->y > ($this->height - $this->GOAL_SIZE) / 2 && $this->ball->y < ($this->height + $this->GOAL_SIZE) / 2){
                if($this->ball->x < $ballOffset){
                    $this->log("Team B do a goal");
                } else {
                    $this->log("Team A do a goal");
                }
            } else {
                $this->log("reset ball");
            }
            $this->resetBall();
        }

        // PLAYER LOGIC
        foreach ($this->players as $player) {

            $simState = [
                "ball" => $this->ball,
                "fieldWidth" => $this->width,
                "fieldHeight" => $this->height,
                "teammates" => array_values(array_filter($this->players, fn($p) => $p->team === $player->team && $p !== $player)),
                "opponents" => array_values(array_filter($this->players, fn($p) => $p->team !== $player->team && $p->bodyCooldown <= 0)),
                "ballTeam" => ($this->findBallOwner()?->team ?? null)
            ];

            $player->checkMarked($simState["opponents"], $this->PLAYER_SIZE * 1.5);
            $player->checkOpponentNear($simState["opponents"], $this->PLAYER_SIZE * 6);

            $action = $player->decide($simState);

            // EXECUTE ACTION
            $player->execute(
                $simState,
                function ($pos) use ($player){
                    // passToCB
                    $dx = $pos["x"] - $this->ball->x;
                    $dy = $pos["y"] - $this->ball->y;
                    $dist = sqrt($dx*$dx + $dy*$dy);
                    $this->applyForceToBall($pos, 2 + min($dist*0.01, 4));
                    $player->ballCooldown = self::BALLCOOLDOWN_PASS;
                    $this->log("{$player->team} {$player->name} pass the ball");
                },
                function ($team) use ($player) {
                    // shootToCB
                    $target = [
                        "x" => ($team === "Team A" ? $this->width : 0),
                        "y" => (($this->height + $this->GOAL_SIZE) * 0.5) - rand(0, $this->GOAL_SIZE)
                    ];
                    $this->applyForceToBall($target, 10);
                    $player->ballCooldown = self::BALLCOOLDOWN_SHOOT;
                    $this->log("{$player->team} {$player->name} shoot to goal");
                }
            );

            $player->update($dt);

            // PLAYER COLLISIONS
            foreach ($this->players as $other) {
                if ($other === $player || $other->bodyCooldown > 0) continue;

                $dx = $player->x - $other->x;
                $dy = $player->y - $other->y;
                $dist = sqrt($dx*$dx + $dy*$dy);

                if ($dist < $this->PLAYER_SIZE * 0.6 && $dist > 0) {
                    $overlap = $this->PLAYER_SIZE * 0.6 - $dist;
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

        $this->ballOwnerTeam = "";
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

        $this->ballOwnerTeam = "";
    }

    private function findBallOwner()
    {
        foreach ($this->players as $p) {
            if ($p->hasBall) return $p;
        }
        return null;
    }

    // ------------------------------------------------------------------------------------
    // BALL POSSESSION LOGIC (idéntico a JS)
    // ------------------------------------------------------------------------------------
    private function handleBallPossession()
    {
        $CONTROL_DISTANCE = $this->PLAYER_SIZE;

        $nearPlayers = array_filter($this->players, function($p) use ($CONTROL_DISTANCE) {
            $dx = $p->x - $this->ball->x;
            $dy = $p->y - $this->ball->y;
            $dist = hypot($dx, $dy);
            return $dist < $CONTROL_DISTANCE && $p->ballCooldown <= 0;
        });

        $nearPlayers = array_values($nearPlayers);

        $currentOwner = $this->findBallOwner();

        if ($currentOwner) {
            // same as JS: challenges, steals, bounces
            $this->resolveOwnerChallenge($currentOwner, $nearPlayers);
            return;
        }

        // NO OWNER: decide new owner or bounce
        if (count($nearPlayers) === 0) return;

        $sameTeam = array_reduce($nearPlayers, fn($c,$p)=> $c && $p->team === $nearPlayers[0]->team, true);

        if ($sameTeam) {
            $newOwner = $nearPlayers[array_rand($nearPlayers)];

            $ballSpeed = hypot($this->ball->vx, $this->ball->vy);

            if ($ballSpeed > 6.0) {
                $angle = rand(0, 10000) / 10000 * 2 * M_PI;
                $reb = $ballSpeed * 0.3;
                $this->ball->vx = cos($angle) * $reb;
                $this->ball->vy = sin($angle) * $reb;

                $newOwner->ballCooldown = self::BALLCOOLDOWN_FAILED_CONTROL;
                $this->log("{$newOwner->team} {$newOwner->name} failed to control fast ball");
            } else {
                $newOwner->hasBall = true;
                $this->ball->x = $newOwner->x;
                $this->ball->y = $newOwner->y;
                $this->ball->vx = 0;
                $this->ball->vy = 0;

                $this->ballOwnerTeam = $newOwner->team;
                $this->log("{$newOwner->team} {$newOwner->name} take ball");
            }

        } else {
            foreach ($nearPlayers as $p) $p->ballCooldown = self::BALLCOOLDOWN_BALL_DISPUTED;

            $this->applyForceToBall([
                "x" => $this->ball->x + (rand(-100,100)),
                "y" => $this->ball->y + (rand(-100,100))
            ], 3);

            $this->log("ball bounces away");
        }
    }

    private function resolveOwnerChallenge($owner, $nearPlayers)
    {
        $opponents = array_filter($nearPlayers, fn($p)=> $p->team !== $owner->team);

        foreach ($opponents as $op) {
            $chance = rand(0, 1000) / 1000;

            if ($chance < 0.2) {
                $owner->hasBall = false;
                $owner->ballCooldown = self::BALLCOOLDOWN_BALL_STEAL;
                $owner->bodyCooldown = self::BODYCOOLDOWN_FAIL_DEFENDING;

                $op->hasBall = true;
                $this->ball->x = $op->x;
                $this->ball->y = $op->y;
                $this->ball->vx = 0;
                $this->ball->vy = 0;

                $this->ballOwnerTeam = $op->team;
                $this->log("{$op->team} {$op->name} steal ball to {$owner->name}");
                return;

            } elseif ($chance < 0.5) {
                $owner->hasBall = false;
                $owner->ballCooldown = self::BALLCOOLDOWN_TAKE_OFF;
                $op->ballCooldown = self::BALLCOOLDOWN_TAKE_OFF;

                $this->applyForceToBall([
                    "x" => $this->ball->x + rand(-100,100)*2,
                    "y" => $this->ball->y + rand(-100,100)*2
                ], 4);

                $this->log("{$op->team} {$op->name} take off ball to {$owner->name}");
                return;

            } else {
                $op->ballCooldown = self::BALLCOOLDOWN_FAIL_DEFENDING;
                $op->bodyCooldown = self::BODYCOOLDOWN_BALL_STEAL;
                $this->log("{$op->team} {$op->name} fails defending {$owner->name}");
            }
        }

        // owner keeps ball
        if ($owner->hasBall) {
            $this->ball->x = $owner->x;
            $this->ball->y = $owner->y;
        }
    }
}