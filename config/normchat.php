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

];
