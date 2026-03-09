<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $defaults = [
            'max_speed' => 0.5, 'accuracy' => 0.5, 'control' => 0.5,
            'reaction'  => 0.5, 'dribble'  => 0.5, 'strength' => 0.5,
            'endurance' => 0.5, 'scan_with_ball' => 0.5, 'scan_without_ball' => 0.5,
            'rules_with_ball' => [], 'rules_without_ball' => [],
        ];

        return [
            'name'           => fake()->unique()->company(),
            'user_id'        => null,
            'wins'           => 0,
            'draws'          => 0,
            'losses'         => 0,
            'matches_played' => 0,
            'configuration'  => [
                array_merge(['name' => 'Player 0', 'default_zone_x' => 50, 'default_zone_y' => 20], $defaults),
                array_merge(['name' => 'Player 1', 'default_zone_x' => 30, 'default_zone_y' => 50], $defaults),
                array_merge(['name' => 'Player 2', 'default_zone_x' => 70, 'default_zone_y' => 70], $defaults),
            ],
        ];
    }
}
