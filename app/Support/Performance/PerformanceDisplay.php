<?php

namespace App\Support\Performance;

use Illuminate\Support\Carbon;

/**
 * Định dạng hiển thị thống nhất module Hiệu suất — ngày giờ cố định 00:00 cho mốc lịch.
 */
final class PerformanceDisplay
{
    public static function dateAtMidnight(Carbon $value): string
    {
        return $value->format('d/m/Y').' 00:00';
    }

    public static function rangeLabel(Carbon $start, Carbon $end): string
    {
        return self::dateAtMidnight($start).' – '.self::dateAtMidnight($end);
    }
}
