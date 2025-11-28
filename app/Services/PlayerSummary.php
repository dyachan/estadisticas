<?php

namespace App\Services;

class PlayerSummary{
    public float $distanceTraveled = 0;
    public float $distanceTraveledWithBall = 0;
    
    public float $timeMarkedWithPossession = 0;
    public float $timeMarkedWithoutPossession = 0;
    
    public float $passesMade = 0;
    public float $passesAchieved = 0;

    public float $shootMade = 0;
    public float $goals = 0;

    public float $stealedBalls = 0;
    public float $takedoffBalls = 0;

    public float $dribbledBalls = 0;

    public float $controledBalls = 0;
    public float $interceptedBalls = 0;
}