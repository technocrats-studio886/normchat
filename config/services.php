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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider OAuth (Connect with ChatGPT / Claude / Gemini)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    | ChatGPT & Claude: API key-based connect (no OAuth available)
    | Gemini: Google OAuth2
    */

    'ai_providers' => [
        // Gemini uses real Google OAuth2
        'gemini' => [
            'client_id' => env('GEMINI_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
            'client_secret' => env('GEMINI_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
            'authorize_url' => env('GEMINI_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'token_url' => env('GEMINI_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
            'userinfo_url' => env('GEMINI_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo'),
            'scopes' => env('GEMINI_SCOPES', 'openid profile email https://www.googleapis.com/auth/generative-language'),
            'redirect' => env('APP_URL') . '/oauth/callback/gemini',
        ],
    ],

];
