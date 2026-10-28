<?php

return [

    'frankfurter' => [
        'url' => env('FRANKFURTER_URL', 'https://api.frankfurter.dev'),
        // Ruta CA opcional para Windows. Un valor vacío/false nunca desactiva TLS.
        'ca_bundle' => env('HTTP_CA_BUNDLE') ?: true,
        'connect_timeout' => (int) env('FRANKFURTER_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('FRANKFURTER_TIMEOUT', 8),
        'cache_seconds' => (int) env('FRANKFURTER_CACHE_SECONDS', 3600),
        'max_age_seconds' => (int) env('FRANKFURTER_MAX_AGE_SECONDS', 604800),
        'retry_seconds' => (int) env('FRANKFURTER_RETRY_SECONDS', 60),
    ],

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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
