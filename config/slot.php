<?php

return [
    'defaults' => [
        'provider_name' => 'buffalo',
        'game_code' => 'BUFFALO1024',
        'display_name' => 'African Buffalo 1024 Ways',
        'grid' => [
            'reels' => 5,
            'rows' => 4,
            'ways' => 1024,
        ],
        'wild_placement' => [
            'excluded_reels' => [0],
            'required_reels' => [1],
        ],
        'wild_substitute_reels' => [1, 2, 3],
        'effective_way_divisor' => 296,
        'base_rtp_reference' => 224.8832,
        'base_rtp_reference_by_volatility' => [
            'low' => 147.2537,
            'medium' => 224.8832,
            'high' => 339.6853,
        ],
        'default_rtp_target' => 96.5,
        'symbols' => [
            'wild' => [
                'id' => 0,
                'label' => 'Wild',
                'pays' => [3 => 150, 4 => 750, 5 => 2500],
            ],
            'scatter' => [
                'id' => 1,
                'label' => 'Scatter',
                'pays' => [3 => 2, 4 => 10, 5 => 50],
            ],
            'buffalo' => [
                'id' => 2,
                'label' => 'Buffalo',
                'pays' => [3 => 80, 4 => 400, 5 => 2000],
            ],
            'elephant' => [
                'id' => 3,
                'label' => 'Elephant',
                'pays' => [3 => 60, 4 => 300, 5 => 1500],
            ],
            'eagle' => [
                'id' => 4,
                'label' => 'Eagle',
                'pays' => [3 => 40, 4 => 200, 5 => 1000],
            ],
            'cougar' => [
                'id' => 5,
                'label' => 'Cougar',
                'pays' => [3 => 30, 4 => 150, 5 => 800],
            ],
            'coin' => [
                'id' => 6,
                'label' => 'Coin',
                'pays' => [3 => 20, 4 => 120, 5 => 600],
            ],
            'ace' => [
                'id' => 7,
                'label' => 'A',
                'pays' => [3 => 15, 4 => 75, 5 => 250],
            ],
            'king' => [
                'id' => 8,
                'label' => 'K',
                'pays' => [3 => 10, 4 => 50, 5 => 180],
            ],
            'queen' => [
                'id' => 9,
                'label' => 'Q',
                'pays' => [3 => 8, 4 => 40, 5 => 140],
            ],
            'jack' => [
                'id' => 10,
                'label' => 'J',
                'pays' => [3 => 6, 4 => 30, 5 => 100],
            ],
            'nine' => [
                'id' => 11,
                'label' => '9',
                'pays' => [3 => 5, 4 => 20, 5 => 80],
            ],
        ],
        'free_spins' => [
            'trigger_symbol' => 'coin',
            'awards' => [
                3 => 8,
                4 => 12,
                5 => 20,
            ],
            'multiplier_by_trigger' => [
                3 => 2,
                4 => 3,
                5 => 5,
            ],
        ],
        'coin_bonus' => [
            'trigger_symbol' => 'coin',
            'min_count' => 5,
            'weighted_multipliers' => [
                ['multiplier' => 5, 'weight' => 30],
                ['multiplier' => 10, 'weight' => 25],
                ['multiplier' => 15, 'weight' => 18],
                ['multiplier' => 25, 'weight' => 15],
                ['multiplier' => 50, 'weight' => 8],
                ['multiplier' => 100, 'weight' => 4],
            ],
        ],
        'volatility_profiles' => [
            'low' => [
                'reels' => [
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'nine', 'jack', 'queen', 'king', 'ace', 'buffalo', 'nine', 'jack', 'queen', 'scatter', 'king', 'ace', 'coin', 'nine', 'jack', 'elephant', 'queen', 'king', 'ace', 'wild', 'coin', 'nine', 'jack', 'queen', 'eagle', 'cougar'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'nine', 'jack', 'queen', 'elephant', 'king', 'ace', 'coin', 'nine', 'jack', 'scatter', 'queen', 'king', 'ace', 'buffalo', 'coin', 'nine', 'jack', 'queen', 'wild', 'king', 'ace', 'coin', 'eagle', 'nine', 'jack', 'cougar'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'nine', 'jack', 'scatter', 'queen', 'king', 'ace', 'coin', 'elephant', 'nine', 'jack', 'queen', 'king', 'ace', 'buffalo', 'coin', 'wild', 'nine', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'cougar', 'jack'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'scatter', 'nine', 'jack', 'queen', 'king', 'ace', 'coin', 'elephant', 'nine', 'jack', 'queen', 'wild', 'king', 'ace', 'buffalo', 'coin', 'nine', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'jack', 'cougar'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'nine', 'jack', 'queen', 'king', 'ace', 'scatter', 'coin', 'nine', 'jack', 'queen', 'elephant', 'king', 'ace', 'buffalo', 'coin', 'wild', 'nine', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'cougar', 'jack'],
                ],
            ],
            'medium' => [
                'reels' => [
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'buffalo', 'nine', 'jack', 'queen', 'king', 'ace', 'scatter', 'coin', 'elephant', 'nine', 'jack', 'wild', 'queen', 'king', 'ace', 'coin', 'eagle', 'nine', 'cougar', 'jack', 'queen', 'king', 'ace', 'buffalo', 'coin', 'nine'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'scatter', 'buffalo', 'nine', 'jack', 'queen', 'king', 'ace', 'coin', 'elephant', 'wild', 'jack', 'queen', 'king', 'ace', 'coin', 'eagle', 'nine', 'cougar', 'jack', 'queen', 'buffalo', 'king', 'ace', 'coin', 'nine', 'jack'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'elephant', 'scatter', 'nine', 'jack', 'queen', 'king', 'ace', 'coin', 'wild', 'buffalo', 'jack', 'queen', 'king', 'ace', 'coin', 'eagle', 'nine', 'cougar', 'jack', 'queen', 'king', 'ace', 'buffalo', 'coin', 'nine', 'jack'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'buffalo', 'nine', 'jack', 'wild', 'queen', 'king', 'ace', 'scatter', 'coin', 'elephant', 'nine', 'jack', 'queen', 'king', 'ace', 'coin', 'eagle', 'nine', 'cougar', 'jack', 'queen', 'king', 'buffalo', 'ace', 'coin', 'nine'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'scatter', 'buffalo', 'nine', 'jack', 'queen', 'king', 'ace', 'coin', 'elephant', 'nine', 'jack', 'wild', 'queen', 'king', 'ace', 'coin', 'eagle', 'nine', 'cougar', 'jack', 'queen', 'buffalo', 'king', 'ace', 'coin', 'nine'],
                ],
            ],
            'high' => [
                'reels' => [
                    ['nine', 'jack', 'queen', 'king', 'ace', 'buffalo', 'nine', 'jack', 'queen', 'wild', 'king', 'ace', 'coin', 'scatter', 'elephant', 'nine', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'buffalo', 'nine', 'cougar', 'jack', 'queen', 'king', 'ace', 'buffalo', 'wild', 'coin'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'buffalo', 'scatter', 'nine', 'jack', 'queen', 'king', 'ace', 'wild', 'elephant', 'coin', 'jack', 'queen', 'king', 'ace', 'eagle', 'buffalo', 'nine', 'cougar', 'jack', 'queen', 'king', 'ace', 'coin', 'buffalo', 'wild', 'nine'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'buffalo', 'wild', 'coin', 'scatter', 'jack', 'queen', 'king', 'ace', 'elephant', 'coin', 'buffalo', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'cougar', 'jack', 'queen', 'king', 'buffalo', 'ace', 'coin', 'wild', 'nine'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'wild', 'buffalo', 'scatter', 'jack', 'queen', 'king', 'ace', 'elephant', 'coin', 'buffalo', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'cougar', 'wild', 'queen', 'king', 'buffalo', 'ace', 'coin', 'jack', 'nine'],
                    ['nine', 'jack', 'queen', 'king', 'ace', 'coin', 'buffalo', 'wild', 'scatter', 'jack', 'queen', 'king', 'ace', 'elephant', 'coin', 'buffalo', 'jack', 'queen', 'king', 'ace', 'eagle', 'coin', 'nine', 'cougar', 'jack', 'queen', 'king', 'buffalo', 'ace', 'wild', 'coin', 'nine'],
                ],
            ],
        ],
    ],
];
