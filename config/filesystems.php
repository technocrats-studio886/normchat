<?php

$configuredNormchatStorageRoot = trim((string) env('NORMCHAT_STORAGE_ROOT', ''));
$defaultNormchatStorageRoot = storage_path('app/normchat');
$normchatStorageRoot = ($configuredNormchatStorageRoot !== '' && is_dir($configuredNormchatStorageRoot))
    ? $configuredNormchatStorageRoot
    : $defaultNormchatStorageRoot;
$normchatStorageRoot = rtrim($normchatStorageRoot, '/');

// File user (lampiran, export, backup, avatar): di S3 bila NORMCHAT_STORAGE=s3 (produksi), masing-masing
// di folder sendiri dalam satu bucket; selain itu di disk lokal. Dibaca lewat App\Services\Files\StoredFiles
// yang men-cache ukuran & isi file kecil di Redis agar tidak selalu meminta ke AWS.
$normchatDisk = fn (string $folder): array => env('NORMCHAT_STORAGE', 'local') === 's3'
    ? [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'root' => $folder,
        'visibility' => 'private',
        // Gagal simpan/baca harus terlihat, bukan diam-diam menghasilkan pesan tanpa file.
        'throw' => true,
        'report' => true,
    ]
    : [
        'driver' => 'local',
        'root' => $normchatStorageRoot.'/'.$folder,
        'throw' => false,
        'report' => false,
    ];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'normchat_root' => [
            'driver' => 'local',
            'root' => $normchatStorageRoot,
            'throw' => false,
            'report' => false,
        ],

        'normchat_exports' => $normchatDisk('exports'),

        'normchat_backups' => $normchatDisk('backups'),

        'normchat_attachments' => $normchatDisk('attachments'),

        'normchat_avatars' => $normchatDisk('avatars'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
