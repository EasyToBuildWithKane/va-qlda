<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App identity (admin-editable via /settings → group "general")
    |--------------------------------------------------------------------------
    |
    | Defaults below are the baseline; the system_settings table overrides
    | them at runtime (see App\Providers\SettingsServiceProvider) and they are
    | shared to Inertia as `app.*` for the sidebar brand + page titles.
    |
    */
    'app_name' => env('APP_DISPLAY_NAME', 'VAschools QLDA'),
    'app_short_name' => env('APP_SHORT_NAME', 'VA'),
    'support_email' => env('SUPPORT_EMAIL', 'phongcongnghe@vaschools.edu.vn'),
    'app_version' => env('APP_DISPLAY_VERSION', '1.0'),

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

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — allowed individual emails (not whole domains)
    |--------------------------------------------------------------------------
    |
    | Comma-separated. Lowercase recommended. Does not open all @gmail.com —
    | only listed addresses may sign in via Google and are limited to Coaching.
    |
    */
    'google_allowed_emails' => array_values(array_unique(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('GOOGLE_ALLOWED_EMAILS', ''))
    )))),

];
