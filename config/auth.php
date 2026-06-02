<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | The default guard is the custom "system" guard backed by the
    | system_accounts table (simulated auth). The Laravel default users table
    | is intentionally not used — see the system spec, Module 2.1.
    |
    */

    'defaults' => [
        'guard' => 'system',
        'passwords' => 'system_accounts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | "system" is the session guard used by the Inertia app. "web" is kept as
    | an alias (same provider) so packages that reference the conventional
    | "web" guard (e.g. Sanctum) continue to resolve.
    |
    */

    'guards' => [
        'system' => [
            'driver' => 'session',
            'provider' => 'system_accounts',
        ],

        'web' => [
            'driver' => 'session',
            'provider' => 'system_accounts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'system_accounts' => [
            'driver' => 'eloquent',
            'model' => App\Models\SystemAccount::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Password reset is not part of the MVP (accounts are provisioned). Config
    | retained but empty; wire a broker here when self-service reset is added.
    |
    */

    'passwords' => [],

    'password_timeout' => 10800,

];
