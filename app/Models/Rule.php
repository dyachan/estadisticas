<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rule extends Model
{
    protected $fillable = ['condition', 'action'];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_rules')
                    ->withPivot('slot', 'priority');
    }
}
