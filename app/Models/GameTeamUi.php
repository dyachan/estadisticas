<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameTeamUi extends Model
{
    protected $table = 'game_team_ui';

    protected $fillable = ['game_team_id', 'color'];
}
