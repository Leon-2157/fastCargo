<?php

return [
    'enabled' => (bool) env('WAG_ENABLED', true),
    'base_url' => env('WAG_BASE_URL', 'http://localhost:8080'),
    'secret' => env('WAG_SECRET'),
    'timeout' => (int) env('WAG_TIMEOUT', 5),
];
