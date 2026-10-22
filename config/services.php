<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
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

    /*
    | Interdotz (SSO, pembayaran via Interdotz, mailbox, API produk). Dinonaktifkan
    | sementara: kodenya tetap ada, route-nya membalas 404 selama `enabled` false.
    */
    'interdotz' => [
        'enabled' => filter_var(env('NORMCHAT_INTERDOTZ_ENABLED', false), FILTER_VALIDATE_BOOL),
        'client_id' => env('INTERDOTZ_CLIENT_ID'),
        'client_secret' => env('INTERDOTZ_CLIENT_SECRET'),
        'admin_bearer_token' => env('INTERDOTZ_ADMIN_BEARER_TOKEN'),
        'api_base' => env('INTERDOTZ_API_BASE', 'https://api-interdotz.technocrats.studio'),
        'sso_base' => env('INTERDOTZ_SSO_BASE', 'https://interdotz.technocrats.studio'),
        'redirect_uri' => env('INTERDOTZ_REDIRECT_URI', env('APP_URL') . '/sso/interdotz/callback'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider API Keys (server-side fallback)
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

];
