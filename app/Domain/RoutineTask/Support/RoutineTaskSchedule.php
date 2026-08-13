<?php

namespace App\Domain\RoutineTask\Support;

use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;

/**
 * Combine work date + clock times, derive actual hours and default progress.
 */
class RoutineTaskSchedule
{
    public static function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($time), $m) !== 1) {
            return null;
        }

        $hour = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $minute = $m[2];
        $second = $m[3] ?? '00';

        return $hour.':'.$minute.':'.$second;
    }

    public static function startedAt(?string $date, ?string $time): ?Carbon
    {
        $normalized = self::normalizeTime($time);
        if ($normalized === null) {
            return null;
        }

        $day = self::normalizeDate($date) ?? Carbon::today()->toDateString();

        return Carbon::parse($day.' '.$normalized);
    }

    public static function endedAt(?string $date, ?string $startTime, ?string $endTime): ?Carbon
    {
        $ended = self::startedAt($date, $endTime);
        if ($ended === null) {
            return null;
        }

        $started = self::startedAt($date, $startTime);
        if ($started !== null && $ended->lte($started)) {
            $ended->addDay();
        }

        return $ended;
    }

    public static function actualHours(?Carbon $started, ?Carbon $ended, mixed $explicit): ?float
    {
        if ($explicit !== null && $explicit !== '') {
            return round((float) $explicit, 2);
        }

        if ($started === null || $ended === null || $ended->lte($started)) {
            return null;
        }

        return round($started->floatDiffInHours($ended), 2);
    }

    public static function progressFor(?TaskStatus $status, mixed $explicit, ?int $current = null): int
    {
        if ($explicit !== null && $explicit !== '') {
            return max(0, min(100, (int) $explicit));
        }

        if ($current !== null && $status === TaskStatus::InProgress && $current > 0 && $current < 100) {
            return $current;
        }

        return match ($status) {
            TaskStatus::Done => 100,
            TaskStatus::InProgress => 50,
            default => 0,
        };
    }

    public static function normalizeDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
