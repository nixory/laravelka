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

    'woo' => [
        'webhook_secret' => env('WOO_WEBHOOK_SECRET'),
        'webhook_token' => env('WOO_WEBHOOK_TOKEN'),
    ],

    'support' => [
        'worker_telegram_url' => env('WORKER_SUPPORT_TELEGRAM_URL', 'https://t.me/egirlz_support'),
    ],

    'telegram' => [
        // Backward compatibility: if split tokens are not set, TELEGRAM_BOT_TOKEN is used for both.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'client_bot_token' => env('TELEGRAM_CLIENT_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
        'admin_bot_token' => env('TELEGRAM_ADMIN_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
        'bot_url' => env('TELEGRAM_BOT_URL', 'https://t.me/egirlz_bot'),
        'bot_secret' => env('TELEGRAM_BOT_SECRET'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        'admin_panel_url' => env('TELEGRAM_ADMIN_PANEL_URL', 'https://ops.egirlz.chat/admin'),
        'worker_panel_url' => env('TELEGRAM_WORKER_PANEL_URL', 'https://ops.egirlz.chat/worker'),
    ],

];
