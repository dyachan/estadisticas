<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /** Simulation player attributes that can be upgraded one step at a time. */
    public const UPGRADEABLE_ATTRIBUTES = [
        'max_speed', 'accuracy', 'control', 'reaction',
        'dribble', 'strength', 'endurance',
        'scan_with_ball', 'scan_without_ball',
    ];

    public const UPGRADE_DELTA = 0.05;

    protected $fillable = [
        'user_id',
        'name',
        'configuration',
        'wins',
        'draws',
        'losses',
        'matches_played',
    ];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function gameMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class)->latest();
    }

    /** Convert to the format expected by MatchSimulation::loadTeams() */
    public function toSimulationFormat(): array
    {
        $players = collect($this->configuration ?? [])->map(fn($p) => [
            'name'           => $p['name']             ?? 'Player',
            'rules'          => [
                $p['rules_with_ball']    ?? [],
                $p['rules_without_ball'] ?? [],
            ],
            'defaultZone'    => [
                'x' => $p['default_zone_x'] ?? 50,
                'y' => $p['default_zone_y'] ?? 25,
            ],
            'maxSpeed'        => $p['max_speed']         ?? 0.5,
            'accuracy'        => $p['accuracy']          ?? 0.5,
            'control'         => $p['control']           ?? 0.5,
            'reaction'        => $p['reaction']          ?? 0.5,
            'dribble'         => $p['dribble']           ?? 0.5,
            'strength'        => $p['strength']          ?? 0.5,
            'endurance'       => $p['endurance']         ?? 0.5,
            'scanWithBall'    => $p['scan_with_ball']    ?? null,
            'scanWithoutBall' => $p['scan_without_ball'] ?? null,
        ])->values()->all();

        return ['players' => $players];
    }

    /** Snapshot stored as opponent_snapshot in game_matches */
    public function toSnapshot(): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'players' => $this->configuration ?? [],
        ];
    }
}
