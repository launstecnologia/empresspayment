<?php

use App\Models\Usuario;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'usuarios'),
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],
    'providers' => [
        'usuarios' => [
            'driver' => 'multi_users',
            'model' => Usuario::class,
        ],
    ],
    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
