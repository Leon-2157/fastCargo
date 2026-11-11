<?php

return [
    'env' => env('DANA_ENV', 'sandbox'),
    'client_id' => env('DANA_CLIENT_ID'),
    'client_secret' => env('DANA_CLIENT_SECRET'),
    'merchant_id' => env('DANA_MERCHANT_ID'),
    'private_key_path' => base_path(env('DANA_PRIVATE_KEY_PATH', 'storage/keys/dana_private.pem')),

    'endpoints' => [
        'sandbox' => 'https://api-sandbox.dana.id',
        'production' => 'https://api.dana.id',
    ],
];
