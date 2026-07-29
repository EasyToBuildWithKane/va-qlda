<?php

/*
|--------------------------------------------------------------------------
| HRM Public API v1 (M2M Sanctum)
|--------------------------------------------------------------------------
| Contract: va-hrm/docs/integrations/qlda.md
| JWT SSO user (HRM_SSO_*) ≠ Bearer HRM_API_TOKEN — không dùng lẫn.
| Danh tính login/upsert chỉ qua HTTP — không đọc hrm_mysql / va_hrm_*.
*/

return [

    'api' => [
        'base_url' => rtrim((string) env('HRM_API_BASE_URL', ''), '/'),
        'token' => (string) env('HRM_API_TOKEN', ''),
        'timeout' => (int) env('HRM_API_TIMEOUT', 10),
        /*
        | true  — verify TLS (mặc định). Nếu curl.cainfo trống (ServBay/Windows),
        |         dùng openssl.cafile / HRM_API_CA_BUNDLE.
        | false — chỉ local khi CA nội bộ; KHÔNG bật production.
        */
        'verify_ssl' => filter_var(env('HRM_API_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('HRM_API_CA_BUNDLE'),
    ],

];
