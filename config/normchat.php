<?php

return [
    // Sesi login berakhir setelah sekian hari tanpa aksi user (App\Http\Middleware\EndIdleSession).
    'session_idle_days' => (int) env('NORMCHAT_SESSION_IDLE_DAYS', 5),

    // Cache Redis untuk file di S3 (App\Services\Files\StoredFiles): file sampai ukuran ini
    // disimpan isinya; ukuran semua file disimpan. File tidak pernah ditimpa, jadi aman di-cache.
    'files' => [
        'cache_max_kb' => 2048,
        'cache_hours' => 24,
    ],

    // Content-Security-Policy (App\Http\Middleware\AddContentSecurityPolicy): alamat realtime Reverb
    // yang dibuka browser, sama dengan VITE_REVERB_* yang dibundel ke JS.
    'csp' => [
        'realtime' => env('VITE_REVERB_HOST') ? [
            (env('VITE_REVERB_SCHEME', 'https') === 'https' ? 'wss://' : 'ws://') . env('VITE_REVERB_HOST') . ':' . env('VITE_REVERB_PORT', 443),
            (env('VITE_REVERB_SCHEME', 'https') === 'https' ? 'wss://' : 'ws://') . env('VITE_REVERB_HOST'),
        ] : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Normkredit Pricing (defaults)
    |--------------------------------------------------------------------------
    |
    | Nilai di sini hanya default. Superadmin bisa menimpanya dari panel
    | /superadmin (disimpan di tabel app_settings), kecuali kurs USD yang
    | sengaja tetap di .env.
    |
    | Pembelian (QRIS saja):
    |   buat grup = group_creation_credits NK; patungan & top-up = kelipatan package_credits NK
    |   harga = NK × rupiah_per_credit, lalu + qris_fee_percent sebagai biaya layanan
    |
    | Potongan per request AI:
    |   biaya OpenAI (USD) × kurs × multiplier model ÷ rupiah_per_credit
    |   → Normkredit (dibulatkan ke atas 0,01 NK). Gambar & transkripsi memakai media_multiplier.
    |
    */

    'pricing' => [
        'rupiah_per_credit' => 120,
        'group_creation_credits' => 250,
        'package_credits' => 125,
        'max_packages_per_payment' => 20,
        'qris_fee_percent' => 0.7,
        'media_multiplier' => 2.0,
        'max_credits_per_request' => 150,
        'max_output_tokens' => 4000,
        'daily_group_credit_limit' => 0,
    ],

    'usd_to_idr' => (float) env('NORMCHAT_USD_TO_IDR', 17950),

    /*
    | Mesin gambar internal. Dipilih per kategori AI (kolom ai_models.image_model dan
    | image_model_precise) dan ditagih dengan multiplier kategorinya. Image API tidak
    | selalu mengembalikan usage; bila tidak ada, dipakai perkiraan fallback_* di bawah.
    */
    'image_models' => [
        'gpt-image-2.5-flare' => [
            'label' => 'Flare (cepat, sehari-hari)',
            'text_input_usd_per_mtok' => 5.00,
            'image_input_usd_per_mtok' => 8.00,
            'image_output_usd_per_mtok' => 30.00,
            'quality' => 'medium',
            'size' => '1024x1024',
            'fallback_output_tokens' => 1056,
            'fallback_image_input_tokens' => 1000,
        ],
        'gpt-image-2.5-sunburst' => [
            'label' => 'Sunburst (presisi & editing)',
            'text_input_usd_per_mtok' => 5.00,
            'image_input_usd_per_mtok' => 8.00,
            'image_output_usd_per_mtok' => 30.00,
            'quality' => 'medium',
            'size' => '1024x1024',
            'fallback_output_tokens' => 1056,
            'fallback_image_input_tokens' => 1000,
        ],
    ],

    // Web search Deep Research: biaya per panggilan alat (USD), ditagih dengan media_multiplier.
    // Token hasil pencarian sudah ikut terhitung di usage input model.
    'web_search' => [
        'usd_per_call' => (float) env('NORMCHAT_WEB_SEARCH_USD_PER_CALL', 0.01),
    ],

    // Transkripsi voice note; ditagih dengan media_multiplier.
    'transcription_model' => [
        'model' => env('NORMCHAT_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'input_usd_per_mtok' => 1.25,
        'output_usd_per_mtok' => 5.00,
    ],

    'superadmin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NORMCHAT_SUPERADMIN_EMAILS', 'superadmin@interdotz.com'))
    ))),

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
