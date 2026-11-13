<?php

return [
    'enabled' => (bool) env('PAYG_ENABLED', true),
    'service_url' => env('PAYG_SERVICE_URL', 'http://payg:8085'),
    'secret' => env('PAYG_SECRET', 'fastcargo-payg-secret-2026'),
    'webhook_url' => env('PAYG_WEBHOOK_URL', 'http://nginx/api/internal/payg/webhook'),
    'timeout' => (int) env('PAYG_TIMEOUT', 10),
];
