<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'wins'           => $this->wins,
            'draws'          => $this->draws,
            'losses'         => $this->losses,
            'matches_played' => $this->matches_played,
            'configuration'  => $this->configuration ?? [],
        ];
    }
}
