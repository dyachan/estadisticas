<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tactic extends Model
{
    protected $fillable = [
        'user_id', 'name',
        'default_zone_x', 'default_zone_y',
        'rules_with_ball', 'rules_without_ball',
    ];

    protected $casts = [
        'rules_with_ball'    => 'array',
        'rules_without_ball' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Convert to the array format expected by MatchSimulation */
    public function toSimulationRules(): array
    {
        return [
            $this->rules_with_ball    ?? [],
            $this->rules_without_ball ?? [],
        ];
    }
}
