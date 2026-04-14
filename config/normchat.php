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
    | Credits per group creation
    |--------------------------------------------------------------------------
    */

    'group_creation_credits' => 12, // 12 normkredit = 30.000 token

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
