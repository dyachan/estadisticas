<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tactic extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tacticRules(): HasMany
    {
        return $this->hasMany(TacticRule::class)->orderBy('slot')->orderBy('priority');
    }

    public function withBallRules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'tactic_rules')
                    ->wherePivot('slot', 'with_ball')
                    ->withPivot('priority')
                    ->orderByPivot('priority');
    }

    public function withoutBallRules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'tactic_rules')
                    ->wherePivot('slot', 'without_ball')
                    ->withPivot('priority')
                    ->orderByPivot('priority');
    }

    /** Convert to the array format expected by MatchSimulation */
    public function toSimulationRules(): array
    {
        return [
            $this->withBallRules->map(fn($r) => ['condition' => $r->condition, 'action' => $r->action])->values()->all(),
            $this->withoutBallRules->map(fn($r) => ['condition' => $r->condition, 'action' => $r->action])->values()->all(),
        ];
    }
}
