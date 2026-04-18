<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kunci tanda tangan
    |--------------------------------------------------------------------------
    |
    | Untuk sederhana & cukup aman, default pakai HS256 dengan secret.
    | Jika ingin rotasi kunci publik/privat (RS256), ubah algo di service.
    |
    */
    'secret' => env('JWT_SECRET', env('APP_KEY')),

    'algo' => env('JWT_ALGO', 'HS256'),

    'issuer' => env('JWT_ISSUER', env('APP_NAME', 'Alsintan')),

    'audience' => env('JWT_AUDIENCE', 'alsintan-mobile'),

    /** TTL access token dalam detik (default 15 menit). */
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15 * 60),

    /** TTL refresh token dalam detik (default 30 hari). */
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 30 * 24 * 60 * 60),

    /** Leeway validasi klaim (detik) untuk toleransi clock skew. */
    'leeway' => (int) env('JWT_LEEWAY', 30),
];
