<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 120),
    'issuer' => env('APP_URL', 'http://localhost'),
    'cookie' => [
        'name' => env('JWT_COOKIE_NAME', 'condominios_token'),
        'path' => env('JWT_COOKIE_PATH', '/'),
        'domain' => env('JWT_COOKIE_DOMAIN'),
        'secure' => env('JWT_COOKIE_SECURE', env('APP_ENV') !== 'local'),
        'http_only' => env('JWT_COOKIE_HTTP_ONLY', true),
        'same_site' => env('JWT_COOKIE_SAME_SITE', 'lax'),
    ],
];
