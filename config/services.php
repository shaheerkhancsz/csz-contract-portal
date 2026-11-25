<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // WHMCS External API — used to link a client to their WHMCS account (lookup only, no sync).
    'whmcs' => [
        'url' => env('WHMCS_URL'),                 // e.g. https://clients.creativesolutionzone.com
        'identifier' => env('WHMCS_API_IDENTIFIER'),
        'secret' => env('WHMCS_API_SECRET'),
        'admin_url' => env('WHMCS_ADMIN_URL'),     // optional, for a deep link into WHMCS admin
        'timeout' => (int) env('WHMCS_TIMEOUT', 10),
    ],

];
