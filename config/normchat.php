<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DU Pricing
    |--------------------------------------------------------------------------
    |
    | Dots Units pricing for each action via Interdotz.
    |
    */

    'du_group_creation' => (int) env('NORMCHAT_DU_GROUP_CREATION', 175),
    'du_patungan' => (int) env('NORMCHAT_DU_PATUNGAN', 25),
    'du_topup_12nk' => (int) env('NORMCHAT_DU_TOPUP_12NK', 150),

    /*
    |--------------------------------------------------------------------------
    | IDR Pricing (Midtrans)
    |--------------------------------------------------------------------------
    */

    'idr_group_creation' => (int) env('NORMCHAT_IDR_GROUP_CREATION', 35000),
    'idr_group_creation_test' => (int) env('NORMCHAT_IDR_GROUP_CREATION_TEST', 1),
    'idr_patungan_min' => (int) env('NORMCHAT_IDR_PATUNGAN_MIN', 5000),
    'idr_topup_12nk' => (int) env('NORMCHAT_IDR_TOPUP_12NK', 35000),
    'idr_topup_24nk' => (int) env('NORMCHAT_IDR_TOPUP_24NK', 70000),
    'idr_topup_48nk' => (int) env('NORMCHAT_IDR_TOPUP_48NK', 140000),
    'idr_topup_100nk' => (int) env('NORMCHAT_IDR_TOPUP_100NK', 280000),

    /*
    |--------------------------------------------------------------------------
    | Credits per group creation
    |--------------------------------------------------------------------------
    */

    'group_creation_credits' => (int) env('NORMCHAT_GROUP_CREATION_CREDITS', 10),
    'join_credits' => (int) env('NORMCHAT_JOIN_CREDITS', 15),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret key for validating incoming webhooks from Interdotz.
    |
    */

    'webhook_secret' => env('NORMCHAT_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Interdotz API Compatibility
    |--------------------------------------------------------------------------
    */

    'allow_interdotz_bearer_only' => filter_var(env('NORMCHAT_ALLOW_INTERDOTZ_BEARER_ONLY', true), FILTER_VALIDATE_BOOL),

    'product_logo_url' => env('NORMCHAT_PRODUCT_LOGO_URL', null),
    'product_icon_url' => env('NORMCHAT_PRODUCT_ICON_URL', null),

];
