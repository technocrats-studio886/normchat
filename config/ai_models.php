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
                'claude-haiku-4-5' => [
                    'label' => 'Claude Haiku 4.5',
                    'multiplier' => 1.0,
                    'description' => 'Cepat & hemat',
                ],
                'claude-sonnet-4-6' => [
                    'label' => 'Claude Sonnet 4.6',
                    'multiplier' => 1.5,
                    'description' => 'Balanced & smart',
                ],
                'claude-opus-4-6' => [
                    'label' => 'Claude Opus 4.6',
                    'multiplier' => 2.0,
                    'description' => 'Paling canggih',
                ],
            ],
        ],

        'gemini' => [
            'label' => 'Gemini (Google)',
            'color' => '#4285F4',
            'models' => [
                'gemini-2.5-flash' => [
                    'label' => 'Gemini 2.5 Flash',
                    'multiplier' => 1.0,
                    'description' => 'Cepat & hemat',
                ],
                'gemini-3-flash-preview' => [
                    'label' => 'Gemini 3 Flash',
                    'multiplier' => 2.0,
                    'description' => 'Balanced & smart',
                ],
                'gemini-3.1-pro-preview' => [
                    'label' => 'Gemini 3.1 Pro',
                    'multiplier' => 2.5,
                    'description' => 'Paling canggih',
                ],
            ],
        ],

    ],

    // Default model per provider (used as fallback)
    'defaults' => [
        'openai' => 'gpt-4o-mini',
        'claude' => 'claude-haiku-4-5',
        'gemini' => 'gemini-2.5-flash',
    ],

];
