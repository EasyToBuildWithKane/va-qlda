<?php

return [
    /*
     * Tỷ giá quy đổi USD → VNĐ khi nhập chi phí bằng USD (UI có thể gửi usd_amount).
     */
    'exchange_rate' => (int) env('AI_ACCOUNT_USD_VND_RATE', 25_500),

    'defaults' => [
        'notify_before_days' => 14,
        'notify_hint' => 'Gửi email và thông báo trước ngày hết hạn license (mặc định 14 ngày).',
        'billing_hint_monthly' => 'Thanh toán theo tháng — nhắc gia hạn mỗi chu kỳ.',
        'billing_hint_yearly' => 'Thanh toán theo năm — nhắc trước khi hết hạn gói năm.',
    ],

    /*
     * Nhắc hết hạn: inbox + email SMTP (MAIL_* trong .env).
     * Người nhận: admin/lead — email từ employees.email (HRM va_hrm SSOT / users.email),
     * refresh từ HRM trước khi gửi; fallback username đăng nhập nếu là email.
     * AI_ACCOUNT_REMINDER_EXTRA_EMAILS: chỉ khi cần thêm hộp thư ngoài danh sách trên.
     */
    'reminder' => [
        'send_email' => env('AI_ACCOUNT_REMINDER_EMAIL', true),
        /** Tối thiểu giờ giữa hai lần gửi (cho phép 8:00 và 14:00 cùng ngày). */
        'min_hours_between' => max(1, (int) env('AI_ACCOUNT_REMINDER_MIN_HOURS', 5)),
        'include_expired' => env('AI_ACCOUNT_REMINDER_INCLUDE_EXPIRED', true),
        'extra_recipients' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_ACCOUNT_REMINDER_EXTRA_EMAILS', '')),
        ))),
        /** Hiển thị trên UI — khớp lịch trong app/Console/Kernel.php */
        'schedule_times' => ['08:00', '14:00'],
    ],
];
