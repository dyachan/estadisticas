<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class FormationsSeeder extends Seeder
{
    public function run(): void
    {
        $formations = [
            [
                'name' => 'Offensive FC',
                'configuration' => [
                    [
                        'name' => 'Goalkeeper', 'default_zone_x' => 50, 'default_zone_y' => 20,
                        'max_speed' => 0.5, 'accuracy' => 0.2, 'control' => 0.5, 'reaction' => 0.8,
                        'dribble' => 0.3, 'strength' => 0.7, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.6,
                        'rules_with_ball'    => [['condition' => 'I has the ball', 'action' => 'Pass the ball']],
                        'rules_without_ball' => [['condition' => 'The ball is in my side', 'action' => 'Go to the ball']],
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 30, 'default_zone_y' => 60,
                        'max_speed' => 0.6, 'accuracy' => 0.7, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.6, 'strength' => 0.4, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.3,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is in other side', 'action' => 'Shoot to goal'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'The ball is in other side', 'action' => 'Go to the ball'],
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 65, 'default_zone_y' => 80,
                        'max_speed' => 0.6, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.3,
                        'dribble' => 0.7, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.3,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is in other side', 'action' => 'Shoot to goal'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'The ball is in other side', 'action' => 'Go to the ball'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Turtles',
                'configuration' => [
                    [
                        'name' => 'Goalkeeper', 'default_zone_x' => 50, 'default_zone_y' => 15,
                        'max_speed' => 0.4, 'accuracy' => 0.2, 'control' => 0.5, 'reaction' => 0.9,
                        'dribble' => 0.2, 'strength' => 0.7, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.7,
                        'rules_with_ball'    => [['condition' => 'I has the ball', 'action' => 'Pass the ball']],
                        'rules_without_ball' => [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']],
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 35, 'default_zone_y' => 35,
                        'max_speed' => 0.5, 'accuracy' => 0.2, 'control' => 0.6, 'reaction' => 0.7,
                        'dribble' => 0.3, 'strength' => 0.7, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.6,
                        'rules_with_ball'    => [['condition' => 'I has the ball', 'action' => 'Pass the ball']],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'I am near a rival', 'action' => 'Go to near rival'],
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 65, 'default_zone_y' => 50,
                        'max_speed' => 0.6, 'accuracy' => 0.7, 'control' => 0.5, 'reaction' => 0.3,
                        'dribble' => 0.7, 'strength' => 0.5, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'The ball is in other side', 'action' => 'Go to the ball'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Team A',
                'configuration' => [
                    [
                        'name' => 'Goalkeeper', 'default_zone_x' => 50, 'default_zone_y' => 20,
                        'max_speed' => 0.7, 'accuracy' => 0.1, 'control' => 0.5, 'reaction' => 0.7,
                        'dribble' => 0.4, 'strength' => 0.6, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.6,
                        'rules_with_ball' => [
                            ['condition' => 'I am marked', 'action' => 'Change side'],
                            ['condition' => 'I has the ball', 'action' => 'Pass the ball'],
                        ],
                        'rules_without_ball' => [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']],
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 50, 'default_zone_y' => 50,
                        'max_speed' => 0.5, 'accuracy' => 0.4, 'control' => 0.7, 'reaction' => 0.5,
                        'dribble' => 0.5, 'strength' => 0.2, 'endurance' => 0.5,
                        'scan_with_ball' => 0.6, 'scan_without_ball' => 0.6,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is in other side', 'action' => 'Pass the ball'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'I am near a rival', 'action' => 'Go to near rival'],
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 75, 'default_zone_y' => 70,
                        'max_speed' => 0.6, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.7, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                            ['condition' => 'I am near a rival', 'action' => 'Change side'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in other side', 'action' => 'Go to the ball'],
                            ['condition' => 'Rival in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'I am near a rival', 'action' => 'Change side'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Pomarola Mecánica',
                'configuration' => [
                    [
                        'name' => 'Goalkeeper', 'default_zone_x' => 50, 'default_zone_y' => 20,
                        'max_speed' => 0.4, 'accuracy' => 0.3, 'control' => 0.5, 'reaction' => 0.8,
                        'dribble' => 0.3, 'strength' => 0.7, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.6,
                        'rules_with_ball'    => [['condition' => 'I has the ball', 'action' => 'Pass the ball']],
                        'rules_without_ball' => [['condition' => 'The ball is near my goal', 'action' => 'Go to the ball']],
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 50, 'default_zone_y' => 40,
                        'max_speed' => 0.3, 'accuracy' => 0.5, 'control' => 0.8, 'reaction' => 0.6,
                        'dribble' => 0.6, 'strength' => 0.1, 'endurance' => 0.4,
                        'scan_with_ball' => 0.7, 'scan_without_ball' => 0.5,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
                            ['condition' => 'I am near a rival', 'action' => 'Pass the ball'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in my side', 'action' => 'Go to the ball'],
                            ['condition' => 'I am near a rival', 'action' => 'Go to near rival'],
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 50, 'default_zone_y' => 60,
                        'max_speed' => 0.7, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.6, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => 'The ball is near rival goal', 'action' => 'Shoot to goal'],
                            ['condition' => 'I has the ball', 'action' => 'Go forward'],
                            ['condition' => 'I am marked', 'action' => 'Change side'],
                        ],
                        'rules_without_ball' => [
                            ['condition' => 'The ball is in other side', 'action' => 'Go to the ball'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($formations as $data) {
            Team::create($data);
        }
    }
}
