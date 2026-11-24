<?php

use Illuminate\Support\Str;

/*
 * Orkestrator antrean (docs/rancangan-worker-dan-file-nov.md). Dijalankan service "queue" di docker-compose.yml
 * dengan `php artisan horizon`. Mesin dipakai bersama Unocoffee produksi: seluruh supervisor maksimal 10 proses.
 */
return [
    'name' => env('HORIZON_NAME', 'normchat'),

    'domain' => env('HORIZON_DOMAIN'),

    // Dasbor bawaan Horizon tidak dipakai (skrip inline-nya ditolak CSP); lihat /superadmin/antrean.
    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),

    'middleware' => ['web', 'auth'],

    // Detik tunggu sebelum LongWaitDetected (App\Listeners\AlertLongQueueWait).
    'waits' => [
        'redis:ai' => 60,
        'redis:notify' => 120,
        'redis:documents' => 600,
        'redis:maintenance' => 1800,
        'redis:default' => 600,
    ],

    // Menit penyimpanan data job di Redis.
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 48,
            'queue' => 48,
        ],
    ],

    'fast_termination' => false,

    // MB untuk proses master Horizon.
    'memory_limit' => 96,

    'defaults' => [
        // Jawaban Normi: per grup berurutan, antar grup paralel.
        'normi-ai' => [
            'connection' => 'redis',
            'queue' => ['ai'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'memory' => 192,
            'tries' => 3,
            'timeout' => 600,
            'maxTime' => 3600,
            'nice' => 0,
        ],
        'normi-notify' => [
            'connection' => 'redis',
            'queue' => ['notify'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'maxTime' => 3600,
            'nice' => 0,
        ],
        'normi-documents' => [
            'connection' => 'redis',
            'queue' => ['documents'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'memory' => 192,
            'tries' => 3,
            'timeout' => 600,
            'maxTime' => 3600,
            'nice' => 5,
        ],
        // "default" ikut di sini: job lama yang masih di Redis saat deploy tetap selesai.
        'normi-background' => [
            'connection' => 'redis',
            'queue' => ['maintenance', 'default'],
            'balance' => false,
            'processes' => 1,
            'memory' => 192,
            'tries' => 5,
            'timeout' => 600,
            'maxTime' => 3600,
            'nice' => 10,
        ],
    ],

    'environments' => [
        'production' => [],
        'local' => [],
        'testing' => [],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],
];
