<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'shopify' => [
        'client_id' => env('SHOPIFY_API_KEY'),
        'client_secret' => env('SHOPIFY_API_SECRET'),
        'redirect' => '/shopify/auth/callback'
    ],

    'zendesk' => [
        'key' => env('ZENDESK_JWT_KEY'),
        'secret' => env('ZENDESK_JWT_SECRET'),
        'support_system_api_name' => env('ZENDESK_SUPPORT_SYSTEM_API_NAME', 'zendesk'),
    ],

    'rates' => [
        'url' => env('RATES_SERVICE_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => null,
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => null,
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => null,
    ],

    'sms4free' => [
        'api_root' => env('SMS4FREE_API_URL'),
        'api_key' => env('SMS4FREE_API_KEY'),
        'user' => env('SMS4FREE_API_USER'),
        'password' => env('SMS4FREE_API_PASS'),
        'sender' => env('SMS4FREE_API_SENDER'),
    ],

    'openAi' => [
        'api_root' => env('OPENAI_API_URL'),
        'api_key' => env('OPENAI_API_KEY'),
        'project_id' => env('OPENAI_PROJECT_ID'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'brave' => [
        'api_key' => env('BRAVE_API_KEY'),
    ],

    'brightdata' => [
        'api_root' => env('BRIGHTDATA_API_URL'),
        'api_key' => env('BRIGHTDATA_API_KEY')
    ],
];
