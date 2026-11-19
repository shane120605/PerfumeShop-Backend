<?php


return [
    'stateful' => [],

    // empty = only "Authorization: Bearer <token>" is accepted (no session cookies)
    'guard' => [],

    // minutes until a token expires (default 7 days)
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 10080),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
