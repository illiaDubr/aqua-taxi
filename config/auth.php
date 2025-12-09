<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Мы используем sanctum для API (включая водителей).
    | Для web — стандартная сессия.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // основной API для клиентов
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // отдельный guard для водителей
        'driver' => [
            'driver' => 'sanctum',
            'provider' => 'drivers',
        ],
        'factory' => [
        'driver' => 'sanctum',
        'provider' => 'factories',
    ],
        // если есть админка
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'factories' => [
        'driver' => 'eloquent',
        'model' => App\Models\Factory::class,
    ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],

        'drivers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Driver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Configuration
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'drivers' => [
            'provider' => 'drivers',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'driver_password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
