<?php

/*
|--------------------------------------------------------------------------
| AI Model Configuration
|--------------------------------------------------------------------------
|
| 1 normkredit = 2.500 token = Rp2.500
| Rp30.000 = 12 normkredit = 30.000 token (minimum per group)
|
| multiplier: berapa kali lipat token yang dicharge ke saldo grup.
|   - Tiap prompt yang di konversi jadi token itu x1.5.
|   - Output gambar = 8.000 token fixed.
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
                    'multiplier' => 1.5,
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
