<?php

namespace App\Support\Performance;

use Illuminate\Support\Carbon;

/**
 * Định dạng hiển thị thống nhất module Hiệu suất — mốc lịch theo ngày (không kèm 00:00).
 */
final class PerformanceDisplay
{
    public static function dateLabel(Carbon $value): string
    {
        return $value->format('d/m/Y');
    }

    /** @deprecated Dùng dateLabel — giữ alias để không phá caller cũ. */
    public static function dateAtMidnight(Carbon $value): string
    {
        return self::dateLabel($value);
    }

    public static function rangeLabel(Carbon $start, Carbon $end): string
    {
        return self::dateLabel($start).' – '.self::dateLabel($end);
    }
}
