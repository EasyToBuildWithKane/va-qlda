<?php

return [
    /*
     * Tỷ giá quy đổi USD → VNĐ khi nhập chi phí bằng USD (UI có thể gửi usd_amount).
     */
    'exchange_rate' => (int) env('AI_ACCOUNT_USD_VND_RATE', 25_500),

    'proposal' => [
        'send_to_default' => 'Phòng Công nghệ & Phòng Kế Toán',
        'objectives_sample' => "Tăng tốc quá trình phân tích và mô hình hóa nghiệp vụ.\nHỗ trợ xây dựng Wireframe và Prototype trực quan.\nGiảm thời gian thiết kế giao diện ban đầu.\nNâng cao chất lượng tài liệu đặc tả nghiệp vụ và yêu cầu hệ thống.",
    ],

    'license_types' => [
        'Free',
        'Pro',
        'Team',
        'Business',
        'Enterprise',
    ],

    /*
     * Nhắc hết hạn: inbox + email SMTP (MAIL_* trong .env).
     * Người nhận: admin/lead — email từ employees.email (cms:sync-employees / CMS users.email),
     * refresh CMS trước khi gửi; fallback username đăng nhập nếu là email.
     * AI_ACCOUNT_REMINDER_EXTRA_EMAILS: chỉ khi cần thêm hộp thư ngoài danh sách trên.
     */
    'reminder' => [
        'send_email' => env('AI_ACCOUNT_REMINDER_EMAIL', true),
        'extra_recipients' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_ACCOUNT_REMINDER_EXTRA_EMAILS', '')),
        ))),
    ],
];
