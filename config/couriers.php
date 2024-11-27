<?php

return [
    'yango' => [
        'api_root' => env('YANGO_API_ROOT'),
        'token' => env('YANGO_TOKEN'),
    ],

    'baldar' => [
        'danino' => [
            'api_root' => env('BALDAR_DANINO_API_ROOT'),
            'user_code' => env('BALDAR_DANINO_USER_CODE'),
        ],
        'dsd' => [
            'api_root' => env('BALDAR_DSD_API_ROOT'),
            'user_code' => env('BALDAR_DSD_USER_CODE'),
        ],
        'ab' => [
            'api_root' => env('BALDAR_AB_API_ROOT'),
            'user_code' => env('BALDAR_AB_USER_CODE'),
        ],
        'tamnoon' => [
            'api_root' => env('BALDAR_TAMNOON_API_ROOT'),
            'user_code' => env('BALDAR_TAMNOON_USER_CODE'),
        ],
        'negev' => [
            'api_root' => env('BALDAR_NEGEV_API_ROOT'),
            'user_code' => env('BALDAR_NEGEV_USER_CODE'),
        ],
    ],

    'run' => [
        'cheetah' => [
            'api_root' => env('RUN_CHEETAH_API_ROOT'),
            'user_code' => env('RUN_CHEETAH_USER_CODE'),
        ],
    ],

    'zigzag' => [
        'api_root' => env('ZIGZAG_API_ROOT'),
        'user' => env('ZIGZAG_USER'),
        'password' => env('ZIGZAG_PASSWORD'),
    ],

    'doordash' => [
        'api_root' => env('DOORDASH_API_ROOT'),
        'developer_id' => env('DOORDASH_DEVELOPER_ID'),
        'key_id' => env('DOORDASH_KEY_ID'),
        'signing_secret' => env('DOORDASH_SIGNING_SECRET'),
    ],

    'done' => [
        'api_root' => env('DONE_API_ROOT'),
        'user' => env('DONE_USER'),
        'password' => env('DONE_PASSWORD'),
    ],

    'shipping_to_go' => [
        'api_root' => env('SHIPPING_TO_GO_API_ROOT'),
        'email' => env('SHIPPING_TO_GO_EMAIL'),
        'password' => env('SHIPPING_TO_GO_PASSWORD'),
        'api_key' => env('SHIPPING_TO_GO_API_KEY'),
        'billing_security_code' => env('SHIPPING_TO_GO_BILLING_SECURITY_CODE'),
    ],

    'ups' => [
        'api_root' => env('UPS_API_ROOT'),
        'client_id' => env('UPS_CLIENT_ID'),
        'client_secret' => env('UPS_CLIENT_SECRET'),
        'account_number' => env('UPS_ACCOUNT_NUMBER'),
    ],

    'lionwheel' => [
        'api_root' => env('LIONWHEEL_API_ROOT'),
    ],

    'getpackage' => [
        'api_root' => env('GETPACKAGE_API_ROOT'),
        'api_key' => env('GETPACKAGE_API_KEY'),
    ],

    'wolt' => [
        'api_root' => env('WOLT_API_ROOT'),
        'venue_id' => env('WOLT_VENUE_ID'),
        'merchant_id' => env('WOLT_MERCHANT_ID'),
        'merchant_key' => env('WOLT_MERCHANT_KEY'),
    ],
];
