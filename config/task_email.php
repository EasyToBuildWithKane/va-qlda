<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task & sprint email notifications (admin-editable via /settings → email)
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TASK_EMAIL_ENABLED', false),

    'from_name' => env('MAIL_FROM_NAME', 'VAschools QLDA'),

    'notify_on_assign' => true,

    /** Scheduled daily digest time (HH:MM) — reserved for future scheduler. */
    'notify_daily_at' => '17:00',

];
