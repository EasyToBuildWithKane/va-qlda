<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contract Lifecycle Management (CLM)
    |--------------------------------------------------------------------------
    |
    | Ngưỡng cảnh báo gia hạn và kênh thông báo. Admin chỉnh runtime tại
    | /settings (tab CLM); giá trị ở đây là mặc định khi bảng cấu hình trống.
    | Lệnh `contracts:send-reminders` đọc config() đã overlay.
    |
    */

    // Các mốc nhắc gia hạn (ngày), phân tách bằng dấu phẩy. Mốc lớn nhất cũng
    // là cửa sổ để chuyển trạng thái sang "Sắp hết hạn".
    'renewal_alert_days' => '90,60,30,7',

    // Bật/tắt toàn bộ cảnh báo gia hạn.
    'alert_enabled' => true,

    // Gửi kèm Telegram khi có cảnh báo (cần telegram.enabled).
    'alert_telegram' => false,
];
