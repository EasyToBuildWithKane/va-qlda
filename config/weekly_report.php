<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM tổng hợp báo cáo tuần
    |--------------------------------------------------------------------------
    |
    | Khi bật + có API key, engine viết lại tóm tắt/nhận định/các thẻ từ dữ liệu
    | Sprint (KPI vẫn do heuristic tính). Super Admin cấu hình tại /settings/ai
    | (overlay lên các key này). Thiếu key hoặc API lỗi → fallback heuristic.
    |
    */

    'llm' => [
        'enabled' => env('WEEKLY_REPORT_LLM_ENABLED', false),

        // openai | anthropic | gemini | nvidia | openai_compatible
        'provider' => env('WEEKLY_REPORT_LLM_PROVIDER', 'openai'),

        'api_key' => env('WEEKLY_REPORT_LLM_API_KEY'),

        'model' => env('WEEKLY_REPORT_LLM_MODEL', 'gpt-4o-mini'),

        // NVIDIA: https://integrate.api.nvidia.com/v1 — bắt buộc với openai_compatible.
        'base_url' => env('WEEKLY_REPORT_LLM_BASE_URL'),

        'timeout' => (int) env('WEEKLY_REPORT_LLM_TIMEOUT', 40),
    ],

];
