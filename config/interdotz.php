<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client Credentials
    |--------------------------------------------------------------------------
    |
    | Kredensial ini diberikan oleh admin Interdotz saat registrasi produk.
    | Simpan nilai ini di environment variable, jangan di-commit ke repo.
    |
    */

    'client_id' => env('INTERDOTZ_CLIENT_ID'),

    'client_secret' => env('INTERDOTZ_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    |
    | Opsi tambahan yang diteruskan ke Guzzle HTTP client.
    | Lihat: https://docs.guzzlephp.org/en/stable/request-options.html
    |
    */

    'http' => [
        'timeout' => env('INTERDOTZ_TIMEOUT', 10),
    ],

];
