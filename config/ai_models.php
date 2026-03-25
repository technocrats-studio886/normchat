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
            'label' => 'ChatGPT (OpenAI)',
            'color' => '#10A37F',
            'models' => [
                'gpt-4o-mini' => [
                    'label' => 'GPT-4o Mini',
                    'multiplier' => 1.0,
                    'description' => 'Cepat & hemat',
                ],
                'gpt-4o' => [
                    'label' => 'GPT-4o',
                    'multiplier' => 1.5,
                    'description' => 'Powerful & balanced',
                ],
                'gpt-4.1' => [
                    'label' => 'GPT-4.1',
                    'multiplier' => 2.0,
                    'description' => 'Paling canggih',
                ],
            ],
        ],

        'claude' => [
            'label' => 'Claude (Anthropic)',
            'color' => '#D97706',
            'models' => [
                'claude-3-5-haiku-latest' => [
                    'label' => 'Claude 3.5 Haiku',
                    'multiplier' => 1.0,
                    'description' => 'Cepat & hemat',
                ],
                'claude-sonnet-4-20250514' => [
                    'label' => 'Claude Sonnet 4',
                    'multiplier' => 1.5,
                    'description' => 'Balanced & smart',
                ],
                'claude-opus-4-20250514' => [
                    'label' => 'Claude Opus 4',
                    'multiplier' => 2.0,
                    'description' => 'Paling canggih',
                ],
            ],
        ],

        'gemini' => [
            'label' => 'Gemini (Google)',
            'color' => '#4285F4',
            'models' => [
                'gemini-2.0-flash' => [
                    'label' => 'Gemini 2.0 Flash',
                    'multiplier' => 1.0,
                    'description' => 'Cepat & hemat',
                ],
                'gemini-2.5-flash-preview-05-20' => [
                    'label' => 'Gemini 2.5 Flash',
                    'multiplier' => 2.0,
                    'description' => 'Balanced & smart',
                ],
                'gemini-2.5-pro-preview-05-06' => [
                    'label' => 'Gemini 2.5 Pro',
                    'multiplier' => 2.5,
                    'description' => 'Paling canggih',
                ],
            ],
        ],

    ],

    // Default model per provider (used as fallback)
    'defaults' => [
        'openai' => 'gpt-4o-mini',
        'claude' => 'claude-3-5-haiku-latest',
        'gemini' => 'gemini-2.0-flash',
    ],

];
