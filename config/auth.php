<?php

use App\Modules\Shared\Models\User;

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
     * Guards terpisah per role — session key berbeda sehingga login di
     * satu subdomain tidak tercampur dengan subdomain lain.
     */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],

        'operator' => [
            'driver' => 'session',
            'provider' => 'operators',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
     * Semua guard pakai tabel users yang sama, dibedakan via user_type.
     * Filter user_type dilakukan di LoginController masing-masing role.
     */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'operators' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
