<?php

namespace App\Services;

class Ball
{
    public float $x;
    public float $y;
    public float $vx = 0.0;
    public float $vy = 0.0;

    public function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function move(): void
    {
        $this->x += $this->vx;
        $this->y += $this->vy;
        $this->vx *= 0.98;
        $this->vy *= 0.98;
    }

    public function applyForce(array $target, float $force): void
    {
        $dx = $target['x'] - $this->x;
        $dy = $target['y'] - $this->y;
        $dist = sqrt($dx * $dx + $dy * $dy);
        if ($dist == 0) return;
        $this->vx = ($dx / $dist) * $force;
        $this->vy = ($dy / $dist) * $force;
    }

    public function clampX(float $min, float $max): void
    {
        $this->x = max($min, min($max, $this->x));
    }

    public function reset(float $x, float $y): void
    {
        $this->x = $x;
        $this->y = $y;
        $this->vx = 0.0;
        $this->vy = 0.0;
    }
}
