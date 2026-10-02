<?php

/*
| Only the origins listed in FRONTEND_URL may call the API from a browser.
| Several origins can be given as a comma-separated list, for example:
| FRONTEND_URL=https://inquiry-portal-kohl.vercel.app,http://localhost:5173
|
| A wildcard such as *.vercel.app is deliberately not used: it would allow
| any Vercel-hosted site, including ones you do not control.
*/

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
)));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [
        '#^https://inquiry-portal-[a-z0-9]+-assures-projects\.vercel\.app$#',
    ],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    // Bearer tokens are used, not cookies.
    'supports_credentials' => false,
];
