<?php

return [
    // This application uses explicit Bearer tokens, not SPA cookie auth.
    'stateful' => [],

    'guard' => ['web'],

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 10080),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' =>
            Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' =>
            Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' =>
            Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
