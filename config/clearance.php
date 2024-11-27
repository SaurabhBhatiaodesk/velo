<?php

return [
    'payme' => [
        'apiRoot' => env('PAYME_API_ROOT'),
        'apiKey' => [
            'il' => env('PAYME_API_KEY_IL'),
            'us' => env('PAYME_API_KEY_US'),
            'default' => env('PAYME_API_KEY_IL'),
        ],
    ],
];
