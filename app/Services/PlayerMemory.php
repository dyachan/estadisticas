<?php

namespace App\Services;

/**
 * Snapshot of the world as seen by a player.
 * Used both as the live simState passed from MatchSimulation each tick,
 * and as the player's cached (potentially stale) memory between refreshes.
 *
 * When used as simState: teammates/opponents are real Player objects.
 * When stored as memory: teammates/opponents are plain snapshots (x, y, name, marked).
 * tick = the simulation tick at which this snapshot was taken.
 */
class PlayerMemory
{
    public ?Ball $ball = null;
    public array $teammates = [];
    public array $opponents = [];
    public ?string $ballTeam = null;
    public array $ballChasers = [];
    public int $tick = -1;
}
