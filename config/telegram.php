<?php

return [

    /*
  |--------------------------------------------------------------------------
  | Telegram Bot (nhóm thông báo)
  |--------------------------------------------------------------------------
  |
  | Tạo bot qua @BotFather, thêm bot vào group, lấy chat_id (thường âm với supergroup).
  | Khi tắt TELEGRAM_ENABLED hoặc thiếu token/chat_id, không gọi API.
  |
  */

    'enabled' => env('TELEGRAM_ENABLED', false),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_CHAT_ID'),

    // Duyệt (chấm điểm) và trả báo cáo (reject) trên /daily-reports/review
    'daily_report_review' => env('TELEGRAM_DAILY_REPORT_REVIEW', true),

];
