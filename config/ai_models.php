<?php

/*
|--------------------------------------------------------------------------
| AI Model Configuration
|--------------------------------------------------------------------------
|
| 1 normkredit = 1.000 token = Rp1.000
|
| multiplier: berapa kali lipat token yang dicharge ke saldo grup.
|   - Misal multiplier 2.0: jika LLM pakai 100K token actual,
|     maka dipotong 200K dari saldo grup.
|   - Model mahal punya multiplier tinggi agar kita tidak rugi.
|
*/

return [

    'providers' => [
        'openai' => [
            'label' => 'NormAI',
            'color' => '#10A37F',
            'models' => [
                'gpt-5' => [
                    'label' => 'NormAI',
                    'multiplier' => 2.0,
                    'description' => 'Managed by platform',
                ],
            ],
        ],
    ],

    // Default model per provider (used as fallback)
    'defaults' => [
        'openai' => 'gpt-5',
    ],

];
