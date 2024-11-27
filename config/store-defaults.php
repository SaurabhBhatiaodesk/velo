<?php

return [
    'pricing_settings' => [
        [
            'threshold' => 0,
            'VELOAPPIO_SAME_DAY' => [
                'price' => 30,
                'active' => false,
            ],
            'VELOAPPIO_NEXT_DAY' => [
                'price' => 30,
                'active' => false,
            ],
            'VELOAPPIO_STANDARD' => [
                'price' => 30,
                'active' => false,
            ],
            'VELOAPPIO_LOCKER2LOCKER' => [
                'price' => 20,
                'active' => false
            ],
            'VELOAPPIO_ON_DEMAND' => [
                'price' => 5,
                'margin' => true,
                'active' => false,
            ],
            'VELOAPPIO_DOMESTIC' => [
                'price' => 5,
                'margin' => true,
                'active' => false,
            ],
            'VELOAPPIO_INTERNATIONAL' => [
                'price' => 5,
                'margin' => true,
                'active' => false,
            ],
        ],
    ],

    'weekly_deliveries_schedule' => [
        // Monday
        1 => [
            'hours' => '12:45',
            'active' => true
        ],
        // Tuesday
        2 => [
            'hours' => '12:45',
            'active' => true
        ],
        // Wednesday
        3 => [
            'hours' => '12:45',
            'active' => true
        ],
        // Thursday
        4 => [
            'hours' => '12:45',
            'active' => true
        ],
        // Friday
        5 => [
            'hours' => '10:45',
            'active' => false
        ],
        // Saturady
        6 => [
            'hours' => '00:00',
            'active' => false
        ],
        // Sunday
        7 => [
            'hours' => '12:45',
            'active' => true
        ]
    ],
];
