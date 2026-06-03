<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password login (dev / E2E only)
    |--------------------------------------------------------------------------
    |
    | Production UI is Google-only. When true, POST /login remains for PHPUnit
    | and Playwright (not exposed on the login page).
    |
    */
    'password_login_enabled' => env('AUTH_PASSWORD_LOGIN', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — allowed email domains (empty = any verified email)
    |--------------------------------------------------------------------------
    */
    'google_allowed_domains' => array_filter(array_map(
        'trim',
        explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', 'vaschools.edu.vn'))
    )),

];
