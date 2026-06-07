<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calendar date/time for daily reports (Vietnam business day by default).
 */
final class DailyReportCalendar
{
    public static function timezone(): string
    {
        return (string) config('daily_report.timezone', 'Asia/Ho_Chi_Minh');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }
}
