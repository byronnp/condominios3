<?php

return [
    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 120),
    'issuer' => env('APP_URL', 'http://localhost'),
];
