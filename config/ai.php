<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', '9router'),

    'timeout' => (int) env('AI_TIMEOUT', 30),

    'fallback_enabled' => (bool) env('AI_FALLBACK_ENABLED', true),

    'providers' => [
        '9router' => [
            'base_url' => env('NINE_ROUTER_BASE_URL', 'http://localhost:20128/v1'),
            'api_key' => env('NINE_ROUTER_API_KEY'),
            'model' => env('NINE_ROUTER_MODEL', 'ag/gemini-3.8-flash-high'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
    ],
];
