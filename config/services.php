<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // Gợi ý Google Workspace trên màn đăng nhập (hd=). Để trống = không giới hạn domain trên UI Google.
        // Campus HCM: hcm.vaschools.edu.vn — HQ: vaschools.edu.vn
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSO HRM → Workspace (ADR-013 phía va-hrm)
    |--------------------------------------------------------------------------
    |
    | HRM là IdP nội bộ (user Google login một lần trên HRM). Workspace redirect
    | sang {base_url}/sso/authorize và nhận về JWT RS256 (TTL ~10 phút),
    | verify offline qua JWKS. Redirect URI luôn là {APP_URL}/auth/hrm/callback
    | — phải whitelist tuyệt đối trên HRM tại /admin/api-clients (client workspace).
    | JWT SSO user ≠ HRM_API_TOKEN (M2M Sanctum — luồng khác).
    |
    */
    'hrm_sso' => [
        'enabled' => filter_var(env('HRM_SSO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string) env('HRM_SSO_BASE_URL', ''), '/'),
        'client_id' => env('HRM_SSO_CLIENT_ID', 'workspace'),
        'audience' => env('HRM_SSO_AUDIENCE', 'workspace'),
        // Phải khớp SSO_ISSUER phía HRM (mặc định = APP_URL của HRM).
        'issuer' => env('HRM_SSO_ISSUER', rtrim((string) env('HRM_SSO_BASE_URL', ''), '/')),
        'jwks_url' => env('HRM_SSO_JWKS_URL'),
        'jwks_cache_ttl' => (int) env('HRM_SSO_JWKS_CACHE_TTL', 3600),
    ],

];
