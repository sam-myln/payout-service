<?php declare(strict_types=1);

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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'provider' => [
        'driver' => env('PROVIDER_DRIVER', 'fake'),
        'base_url' => env('PROVIDER_BASE_URL'),
        'timeout_connect' => (float) env('PROVIDER_TIMEOUT_CONNECT', 2.0),
        'timeout_read' => (float) env('PROVIDER_TIMEOUT_READ', 5.0),
        'webhook_secret' => env('PROVIDER_WEBHOOK_SECRET'),
        'max_attempts' => (int) env('PROVIDER_MAX_ATTEMPTS', 8),
        'fake_scenario' => env('PROVIDER_FAKE_SCENARIO', 'success'),
    ],

];
