<?php

/*
|--------------------------------------------------------------------------
| Performance Analytics & Work Audit
|--------------------------------------------------------------------------
|
| Trọng số tính điểm hiệu suất khách quan (từ dữ liệu task), ngưỡng workload
| và ngưỡng cho rule engine "AI Insights" (heuristic). Tách khỏi code để dễ
| chỉnh và sau này có thể đưa lên system_settings (admin-editable).
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

    // ── Ngưỡng rule engine (Insights heuristic) ──────────────────────────
    'insights' => [
        'late_streak_weeks' => 3,     // trễ liên tiếp ≥ N tuần → cảnh báo
        'project_dominance_pct' => 50, // 1 dự án chiếm ≥ % workload 1 người → lưu ý
        'on_time_good_pct' => 90,      // đúng hạn ≥ % → khen
        'overdue_alert' => 3,          // ≥ N task quá hạn → cảnh báo
        'low_score' => 50,             // điểm hiệu suất < ngưỡng → cảnh báo
    ],
];
