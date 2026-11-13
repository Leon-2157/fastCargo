<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'custom'),

    'timeout' => (int) env('AI_TIMEOUT', 30),

    'fallback_enabled' => (bool) env('AI_FALLBACK_ENABLED', true),

    'providers' => [
        'custom' => [
            'name' => env('CUSTOM_AI_NAME', 'Custom AI'),
            'base_url' => env('CUSTOM_AI_BASE_URL', env('NINE_ROUTER_BASE_URL', 'http://localhost:20128/v1')),
            'api_key' => env('CUSTOM_AI_API_KEY', env('NINE_ROUTER_API_KEY')),
            'model' => env('CUSTOM_AI_MODEL', env('NINE_ROUTER_MODEL', 'ag/gemini-3.8-flash-high')),
        ],
        // Backward-compatibility alias
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
