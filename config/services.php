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

        'interdotz' => [
        'client_id' => env('INTERDOTZ_CLIENT_ID'),
        'client_secret' => env('INTERDOTZ_CLIENT_SECRET'),
        'admin_bearer_token' => env('INTERDOTZ_ADMIN_BEARER_TOKEN'),
        'api_base' => env('INTERDOTZ_API_BASE', 'https://api-interdotz.technocrats.studio'),
        'sso_base' => env('INTERDOTZ_SSO_BASE', 'https://interdotz.technocrats.studio'),
        'redirect_uri' => env('INTERDOTZ_REDIRECT_URI', env('APP_URL') . '/sso/interdotz/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider API Keys (server-side fallback)
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
