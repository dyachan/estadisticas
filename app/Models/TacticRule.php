<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TacticRule extends Model
{
    public $timestamps = false;

    protected $fillable = ['tactic_id', 'rule_id', 'slot', 'priority'];

    public function tactic(): BelongsTo
    {
        return $this->belongsTo(Tactic::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
