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

    /**
     * Whether the given report date is the current business day. Compares
     * calendar dates only (report dates are stored date-only), resolved in the
     * report timezone so a recall window never drifts across midnight UTC.
     */
    public static function isToday(mixed $date): bool
    {
        $value = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) $date;

        return CarbonImmutable::parse($value)->toDateString() === self::today();
    }
}
