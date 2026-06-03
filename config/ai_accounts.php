<?php

return [
    /*
     * Tỷ giá quy đổi USD → VNĐ khi nhập chi phí bằng USD (UI có thể gửi usd_amount).
     */
    'exchange_rate' => (int) env('AI_ACCOUNT_USD_VND_RATE', 25_500),

    'proposal' => [
        'send_to_default' => "Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán",
        'objectives_sample' => "Tăng tốc quá trình phân tích và mô hình hóa nghiệp vụ.\nHỗ trợ xây dựng Wireframe và Prototype trực quan.\nGiảm thời gian thiết kế giao diện ban đầu.\nNâng cao chất lượng tài liệu đặc tả nghiệp vụ và yêu cầu hệ thống.",
    ],

    /** Giấy đề nghị thanh toán (KT.BM.03) — xuất PDF từ phiếu đề xuất. */
    'payment_request' => [
        'form_code' => 'KT.BM.03',
        'company_unit' => 'Công ty CP Văn hóa Giáo dục Việt Mỹ',
        'department' => 'Phòng Công Nghệ',
        'send_to' => 'Ban Tổng Giám Đốc',
        'payment_method' => 'Thanh toán bằng thẻ kế toán',
    ],

    'defaults' => [
        'notify_before_days' => 14,
        'notify_hint' => 'Gửi email và thông báo trước ngày hết hạn license (mặc định 14 ngày).',
        'billing_hint_monthly' => 'Thanh toán theo tháng — nhắc gia hạn mỗi chu kỳ.',
        'billing_hint_yearly' => 'Thanh toán theo năm — nhắc trước khi hết hạn gói năm.',
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
        /** Tối thiểu giờ giữa hai lần gửi (cho phép 8:00 và 14:00 cùng ngày). */
        'min_hours_between' => max(1, (int) env('AI_ACCOUNT_REMINDER_MIN_HOURS', 5)),
        'include_expired' => env('AI_ACCOUNT_REMINDER_INCLUDE_EXPIRED', true),
        /** Hết hạn + chưa thanh toán gia hạn → email/inbox nhắc riêng. */
        'include_unpaid_renewal' => env('AI_ACCOUNT_REMINDER_UNPAID_RENEWAL', true),
        'extra_recipients' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('AI_ACCOUNT_REMINDER_EXTRA_EMAILS', '')),
        ))),
        /** Hiển thị trên UI — khớp lịch trong app/Console/Kernel.php */
        'schedule_times' => ['08:00', '14:00'],
    ],
];
