<?php

/*
|--------------------------------------------------------------------------
| Performance Analytics & Work Audit
|--------------------------------------------------------------------------
|
| Trọng số tính điểm hiệu suất khách quan (từ dữ liệu task), ngưỡng workload.
| Tách khỏi code để dễ chỉnh và sau này có thể đưa lên system_settings (admin-editable).
|
*/

return [

    // Lịch nghiệp vụ — dùng chung với daily report để các mốc tuần khớp nhau.
    'timezone' => env('DAILY_REPORT_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Kỳ mặc định khi mở dashboard (week|month|quarter|year).
    'default_period' => 'month',

    // ── Điểm hiệu suất khách quan (thang 0–100) ──────────────────────────
    // performanceScore = completion*wc + onTime*wo + quality*wq (đã chuẩn hóa).
    'scoring' => [
        'weights' => [
            'completion' => 0.45, // % task hoàn thành trên tổng task được giao trong kỳ
            'on_time' => 0.35,    // % task hoàn thành đúng hạn (completed_at <= due_date)
            'quality' => 0.20,    // 100 − tỉ lệ task bị Blocked (proxy cho chất lượng/độ trơn)
        ],
    ],

    // ── Workload & Capacity ──────────────────────────────────────────────
    // Số task đang mở (chưa Done) để phân loại tải. Xanh ≤ healthy_max,
    // Đỏ ≥ overloaded_min, còn lại Vàng (cần theo dõi).
    'workload' => [
        'healthy_max' => 8,
        'overloaded_min' => 15,
    ],
];
