<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins for the Operix API.
    | In production, restrict to the exact domain(s) of the frontend.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'docs/api',
        'docs/api.json',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-XSRF-TOKEN',
        'X-Tenant-Slug',
        'Cache-Control',
    ],

    'exposed_headers' => [
        'X-Request-Id',
    ],

    'max_age' => 86400, // 24h preflight cache

    'supports_credentials' => true,

];
