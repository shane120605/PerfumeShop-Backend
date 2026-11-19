<?php

return [
    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_merge(
        [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:5174',
            'https://onlineperfumeshop-2tt7.vercel.app',
        ],
        explode(',', env('CORS_ALLOWED_ORIGINS', ''))
    )),

    'allowed_origins_patterns' => [
        '#^https://onlineperfumeshop-2tt7(-[a-z0-9-]+)?\.vercel\.app$#',
        '#^https://onlineperfumeshop-2tt7-[a-z0-9-]+-shane120605s-projects\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer-token API: cookies are not used, so credentials stay off.
    'supports_credentials' => false,
];