<?php

namespace App\Services;

/**
 * Describes the action a player wants to perform this tick.
 * Returned by Player::execute() and processed by MatchSimulation.
 */
class PlayerAction
{
    public const PASS  = 'pass';
    public const SHOOT = 'shoot';

    public string $type;
    public ?object $passTarget = null;

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function pass(object $target): self
    {
        $action = new self(self::PASS);
        $action->passTarget = $target;
        return $action;
    }

    public static function shoot(): self
    {
        return new self(self::SHOOT);
    }
}
