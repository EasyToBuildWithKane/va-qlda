<?php

namespace App\Support;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskHoursTiming;
use App\Support\Enums\TaskSlaResult;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TaskCompletion
{
    private const HOURS_EPSILON = 0.05;

    public static function actorMayUnlockStatus(SystemAccount $actor): bool
    {
        return $actor->hasRole(SystemRole::Admin);
    }

    /**
     * @throws ValidationException
     */
    public static function guardStatusChange(Task $task, ?string $newStatus, SystemAccount $actor): void
    {
        if ($newStatus === null) {
            return;
        }

        $current = $task->status->value;
        if ($newStatus === $current) {
            return;
        }

        if ($current === TaskStatus::Done->value && ! self::actorMayUnlockStatus($actor)) {
            throw ValidationException::withMessages([
                'status' => 'Công việc đã hoàn thành — không thể đổi trạng thái. Liên hệ quản trị nếu cần mở khóa.',
            ]);
        }

        if ($newStatus === TaskStatus::Done->value) {
            return;
        }

        if ($current === TaskStatus::Done->value && self::actorMayUnlockStatus($actor)) {
            return;
        }
    }

    /**
     * @throws ValidationException
     */
    public static function guardCompletePayload(Task $task, array $payload): void
    {
        $newStatus = $payload['status'] ?? null;
        if ($newStatus !== TaskStatus::Done->value || $task->status === TaskStatus::Done) {
            return;
        }

        $actual = $payload['actual_hours'] ?? null;
        if ($actual === null || ! is_numeric($actual) || (float) $actual <= 0) {
            throw ValidationException::withMessages([
                'actual_hours' => 'Vui lòng nhập số giờ thực tế khi hoàn thành công việc.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function completionAttributes(Task $task, float $actualHours, ?string $note): array
    {
        $completedAt = now();
        $estimate = $task->estimate_hours !== null ? (float) $task->estimate_hours : null;

        $hoursTiming = self::resolveHoursTiming($estimate, $actualHours);
        $slaResult = self::resolveSlaResult($task, $actualHours, $completedAt);

        return [
            'actual_hours' => round($actualHours, 2),
            'completion_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'completed_at' => $completedAt,
            'hours_timing' => $hoursTiming?->value,
            'sla_result' => $slaResult?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clearCompletionAttributes(): array
    {
        return [
            'actual_hours' => null,
            'completion_note' => null,
            'completed_at' => null,
            'hours_timing' => null,
            'sla_result' => null,
        ];
    }

    public static function resolveHoursTiming(?float $estimateHours, float $actualHours): ?TaskHoursTiming
    {
        if ($estimateHours === null || $estimateHours <= 0) {
            return null;
        }

        if ($actualHours < $estimateHours - self::HOURS_EPSILON) {
            return TaskHoursTiming::Early;
        }

        if ($actualHours > $estimateHours + self::HOURS_EPSILON) {
            return TaskHoursTiming::OverPlan;
        }

        return TaskHoursTiming::OnPlan;
    }

    public static function resolveSlaResult(Task $task, float $actualHours, Carbon $completedAt): ?TaskSlaResult
    {
        $estimate = $task->estimate_hours !== null ? (float) $task->estimate_hours : null;

        if ($estimate !== null && $estimate > 0 && $actualHours > $estimate + self::HOURS_EPSILON) {
            return TaskSlaResult::Exceeded;
        }

        $deadline = TaskTimeliness::estimateDeadline($task);
        if ($deadline && $completedAt->greaterThan($deadline)) {
            return TaskSlaResult::Exceeded;
        }

        if ($estimate !== null && $estimate > 0) {
            return TaskSlaResult::Met;
        }

        if ($deadline) {
            return TaskSlaResult::Met;
        }

        return null;
    }
}
