<?php

namespace App\Support;

use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;

/**
 * Quy tắc "trễ" task — dùng chung API và đối soát.
 *
 * 1. Quá hạn ngày: due_date &lt; hôm nay và chưa hoàn thành.
 * 2. Vượt giờ ước tính: thời điểm hiện tại (hoặc lúc hoàn thành) &gt; work_started_at + estimate_hours.
 */
class TaskTimeliness
{
    /** @var list<string> */
    public const ACTIVE_STATUSES = ['in_progress', 'in_review', 'blocked'];

    public static function workStartedAt(Task $task): ?Carbon
    {
        if ($task->work_started_at) {
            return $task->work_started_at->copy();
        }

        if (! in_array($task->status->value, self::ACTIVE_STATUSES, true)) {
            return null;
        }

        if ($task->start_date) {
            return $task->start_date->copy()->setTime(9, 0, 0);
        }

        return $task->created_at?->copy();
    }

    public static function estimateDeadline(Task $task): ?Carbon
    {
        $hours = $task->estimate_hours !== null ? (float) $task->estimate_hours : 0;
        if ($hours <= 0) {
            return null;
        }

        $started = self::workStartedAt($task);

        return $started?->copy()->addMinutes((int) round($hours * 60));
    }

    public static function isDone(Task $task): bool
    {
        return $task->status === TaskStatus::Done;
    }

    public static function isDateOverdue(Task $task): bool
    {
        if (self::isDone($task) || ! $task->due_date) {
            return false;
        }

        return $task->due_date->copy()->startOfDay()->lt(Carbon::today());
    }

    public static function isEstimateOverrun(Task $task): bool
    {
        $deadline = self::estimateDeadline($task);
        if (! $deadline) {
            return false;
        }

        if (self::isDone($task)) {
            return $task->updated_at->greaterThan($deadline);
        }

        if (! in_array($task->status->value, self::ACTIVE_STATUSES, true)) {
            return false;
        }

        return now()->greaterThan($deadline);
    }

    public static function isLate(Task $task): bool
    {
        return self::isDateOverdue($task) || self::isEstimateOverrun($task);
    }

    /**
     * @return list<'due_date'|'estimate'>
     */
    public static function lateReasons(Task $task): array
    {
        $reasons = [];
        if (self::isDateOverdue($task)) {
            $reasons[] = 'due_date';
        }
        if (self::isEstimateOverrun($task)) {
            $reasons[] = 'estimate';
        }

        return $reasons;
    }

    public static function syncWorkStartedAt(Task $task, ?string $previousStatus = null): void
    {
        $current = $task->status->value;
        $wasActive = $previousStatus && in_array($previousStatus, self::ACTIVE_STATUSES, true);
        $isActive = in_array($current, self::ACTIVE_STATUSES, true);

        if ($isActive && ! $wasActive && ! $task->work_started_at) {
            $task->forceFill(['work_started_at' => now()])->saveQuietly();
        }

        if ($current === TaskStatus::Todo->value && $wasActive) {
            $task->forceFill(['work_started_at' => null])->saveQuietly();
        }
    }
}
