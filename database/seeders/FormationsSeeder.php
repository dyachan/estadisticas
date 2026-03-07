<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\PlayerRules;
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
                        'rules_with_ball'    => [['condition' => PlayerRules::C_HAS_BALL,        'action' => PlayerRules::A_PASS]],          // "I has the ball" → "Pass the ball"
                        'rules_without_ball' => [['condition' => PlayerRules::C_BALL_IN_MY_SIDE, 'action' => PlayerRules::A_GO_TO_BALL]],   // "The ball is in my side" → "Go to the ball"
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 30, 'default_zone_y' => 60,
                        'max_speed' => 0.6, 'accuracy' => 0.7, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.6, 'strength' => 0.4, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.3,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_SHOOT],       // "The ball is in other side" → "Shoot to goal"
                            ['condition' => PlayerRules::C_HAS_BALL,           'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE,    'action' => PlayerRules::A_GO_TO_BALL],  // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],  // "The ball is in other side" → "Go to the ball"
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 65, 'default_zone_y' => 80,
                        'max_speed' => 0.6, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.3,
                        'dribble' => 0.7, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.3,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_SHOOT],       // "The ball is in other side" → "Shoot to goal"
                            ['condition' => PlayerRules::C_HAS_BALL,           'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE,    'action' => PlayerRules::A_GO_TO_BALL],  // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],  // "The ball is in other side" → "Go to the ball"
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
                        'rules_with_ball'    => [['condition' => PlayerRules::C_HAS_BALL,           'action' => PlayerRules::A_PASS]],         // "I has the ball" → "Pass the ball"
                        'rules_without_ball' => [['condition' => PlayerRules::C_BALL_NEAR_MY_GOAL,  'action' => PlayerRules::A_GO_TO_BALL]],   // "The ball is near my goal" → "Go to the ball"
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 35, 'default_zone_y' => 35,
                        'max_speed' => 0.5, 'accuracy' => 0.2, 'control' => 0.6, 'reaction' => 0.7,
                        'dribble' => 0.3, 'strength' => 0.7, 'endurance' => 0.6,
                        'scan_with_ball' => 0.3, 'scan_without_ball' => 0.6,
                        'rules_with_ball'    => [['condition' => PlayerRules::C_HAS_BALL,        'action' => PlayerRules::A_PASS]],            // "I has the ball" → "Pass the ball"
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],       // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,      'action' => PlayerRules::A_GO_TO_NEAR_RIVAL], // "I am near a rival" → "Go to near rival"
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 65, 'default_zone_y' => 50,
                        'max_speed' => 0.6, 'accuracy' => 0.7, 'control' => 0.5, 'reaction' => 0.3,
                        'dribble' => 0.7, 'strength' => 0.5, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_NEAR_RIVAL_GOAL, 'action' => PlayerRules::A_SHOOT],       // "The ball is near rival goal" → "Shoot to goal"
                            ['condition' => PlayerRules::C_HAS_BALL,             'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE,    'action' => PlayerRules::A_GO_TO_BALL],    // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],    // "The ball is in other side" → "Go to the ball"
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
                            ['condition' => PlayerRules::C_AM_MARKED, 'action' => PlayerRules::A_CHANGE_SIDE], // "I am marked" → "Change side"
                            ['condition' => PlayerRules::C_HAS_BALL,  'action' => PlayerRules::A_PASS],        // "I has the ball" → "Pass the ball"
                        ],
                        'rules_without_ball' => [['condition' => PlayerRules::C_BALL_NEAR_MY_GOAL, 'action' => PlayerRules::A_GO_TO_BALL]], // "The ball is near my goal" → "Go to the ball"
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 50, 'default_zone_y' => 50,
                        'max_speed' => 0.5, 'accuracy' => 0.4, 'control' => 0.7, 'reaction' => 0.5,
                        'dribble' => 0.5, 'strength' => 0.2, 'endurance' => 0.5,
                        'scan_with_ball' => 0.6, 'scan_without_ball' => 0.6,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_PASS],       // "The ball is in other side" → "Pass the ball"
                            ['condition' => PlayerRules::C_HAS_BALL,           'action' => PlayerRules::A_GO_FORWARD], // "I has the ball" → "Go forward"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],       // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,      'action' => PlayerRules::A_GO_TO_NEAR_RIVAL], // "I am near a rival" → "Go to near rival"
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 75, 'default_zone_y' => 70,
                        'max_speed' => 0.6, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.7, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_NEAR_RIVAL_GOAL, 'action' => PlayerRules::A_SHOOT],       // "The ball is near rival goal" → "Shoot to goal"
                            ['condition' => PlayerRules::C_HAS_BALL,             'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,           'action' => PlayerRules::A_CHANGE_SIDE], // "I am near a rival" → "Change side"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],    // "The ball is in other side" → "Go to the ball"
                            ['condition' => PlayerRules::C_RIVAL_IN_MY_SIDE,   'action' => PlayerRules::A_GO_TO_BALL],    // "Rival in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,         'action' => PlayerRules::A_CHANGE_SIDE],   // "I am near a rival" → "Change side"
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
                        'rules_with_ball'    => [['condition' => PlayerRules::C_HAS_BALL,          'action' => PlayerRules::A_PASS]],        // "I has the ball" → "Pass the ball"
                        'rules_without_ball' => [['condition' => PlayerRules::C_BALL_NEAR_MY_GOAL, 'action' => PlayerRules::A_GO_TO_BALL]], // "The ball is near my goal" → "Go to the ball"
                    ],
                    [
                        'name' => 'Defender', 'default_zone_x' => 50, 'default_zone_y' => 40,
                        'max_speed' => 0.3, 'accuracy' => 0.5, 'control' => 0.8, 'reaction' => 0.6,
                        'dribble' => 0.6, 'strength' => 0.1, 'endurance' => 0.4,
                        'scan_with_ball' => 0.7, 'scan_without_ball' => 0.5,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_NEAR_RIVAL_GOAL, 'action' => PlayerRules::A_SHOOT],      // "The ball is near rival goal" → "Shoot to goal"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,           'action' => PlayerRules::A_PASS],        // "I am near a rival" → "Pass the ball"
                            ['condition' => PlayerRules::C_HAS_BALL,             'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_MY_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],       // "The ball is in my side" → "Go to the ball"
                            ['condition' => PlayerRules::C_NEAR_RIVAL,      'action' => PlayerRules::A_GO_TO_NEAR_RIVAL], // "I am near a rival" → "Go to near rival"
                        ],
                    ],
                    [
                        'name' => 'Striker', 'default_zone_x' => 50, 'default_zone_y' => 60,
                        'max_speed' => 0.7, 'accuracy' => 0.8, 'control' => 0.5, 'reaction' => 0.4,
                        'dribble' => 0.6, 'strength' => 0.3, 'endurance' => 0.5,
                        'scan_with_ball' => 0.5, 'scan_without_ball' => 0.2,
                        'rules_with_ball' => [
                            ['condition' => PlayerRules::C_BALL_NEAR_RIVAL_GOAL, 'action' => PlayerRules::A_SHOOT],       // "The ball is near rival goal" → "Shoot to goal"
                            ['condition' => PlayerRules::C_HAS_BALL,             'action' => PlayerRules::A_GO_FORWARD],  // "I has the ball" → "Go forward"
                            ['condition' => PlayerRules::C_AM_MARKED,            'action' => PlayerRules::A_CHANGE_SIDE], // "I am marked" → "Change side"
                        ],
                        'rules_without_ball' => [
                            ['condition' => PlayerRules::C_BALL_IN_OTHER_SIDE, 'action' => PlayerRules::A_GO_TO_BALL],    // "The ball is in other side" → "Go to the ball"
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
