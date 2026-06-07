<?php

/*
|--------------------------------------------------------------------------
| Daily Report configuration
|--------------------------------------------------------------------------
|
| Scoring weights, grade thresholds and submission rules. Kept in config for
| the MVP; will migrate to the system_settings table (admin-editable) in V2.
|
*/

return [

    // Business calendar for "báo cáo hôm nay", working-day gate, and late cutoff.
    'timezone' => env('DAILY_REPORT_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Weighted contribution of each dimension to the total score. Must sum to 1.
    'weights' => [
        'task_completion' => 0.30,
        'skill_score' => 0.20,
        'attitude_score' => 0.15,
        'kaizen_score' => 0.15,
        'expertise_score' => 0.20,
    ],

    // Grade thresholds (descending). A total >= threshold earns the grade.
    'grades' => [
        'S' => 9.0,
        'A' => 8.0,
        'B' => 6.5,
        'C' => 5.0,
        // anything below the lowest threshold => D
    ],

    // ISO weekday numbers allowed for submission (1 = Mon … 7 = Sun). Mon–Sat.
    'working_days' => [1, 2, 3, 4, 5, 6],

    // Submissions at/after this local time are flagged as late.
    'late_after' => '18:00',

    // Tolerance band (points) for the weekly "stable" trend classification.
    'trend_tolerance' => 0.3,
];
