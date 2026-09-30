<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma separated list, e.g. "https://globalnet.vercel.app,http://localhost:5173".
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        env('FRONTEND_URL', 'http://localhost:5173'),
    )))),

    // Optional regex for Vercel preview deployments, e.g. "#^https://globalnet-.*\.vercel\.app$#".
    'allowed_origins_patterns' => array_filter([env('CORS_ALLOWED_ORIGINS_PATTERN')]),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Idempotent-Replayed', 'Retry-After', 'Content-Disposition'],

    'max_age' => 3600,

    // Bearer tokens are used instead of cookies, so credentials are not needed.
    'supports_credentials' => false,

];
