<?php

return [
    'flex' => [
        'member' => [
            'can_add' => false,
            'included' => 0,
            'limit' => 0,
        ],
        'address' => [
            'can_add' => false,
            'included' => 1,
            'limit' => 1,
        ],
        'integration' => [
            'can_add' => false,
            'included' => 0,
            'limit' => 0,
        ],
    ],
    'plus' => [
        'member' => [
            'can_add' => true,
            'included' => 0,
            'limit' => 4,
        ],
        'address' => [
            'can_add' => false,
            'included' => 1,
            'limit' => 1,
        ],
        'integration' => [
            'can_add' => true,
            'included' => 0,
            'limit' => -1,
        ],
    ],
    'pro' => [
        'member' => [
            'can_add' => true,
            'included' => 4,
            'limit' => -1,
        ],
        'address' => [
            'can_add' => true,
            'included' => 1,
            'limit' => -1,
        ],
        'integration' => [
            'can_add' => true,
            'included' => 0,
            'limit' => -1,
        ],
    ],
];
