<?php

namespace App\Application\Task;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskProgress;
use App\Support\TaskTimeliness;

class PatchTaskUseCase
{
    /**
     * Kanban / Gantt lightweight patch with status → progress mapping.
     *
     * @param  array<string, mixed>  $validated
     * @return array{task: Task, flash: string}
     */
    public function execute(Task $task, array $validated, SystemAccount $actor): array
    {
        $newStatus = $validated['status'] ?? $task->status->value;

        if (isset($validated['status'])) {
            $validated['progress'] = TaskProgress::fromStatus($newStatus);
        } else {
            unset($validated['progress']);
        }

        $previousStatus = $task->status->value;
        $task->update($validated);
        $fresh = $task->fresh();
        TaskTimeliness::syncWorkStartedAt($fresh, $previousStatus);

        if ($fresh->wasChanged('status')) {
            TaskActivityLogger::statusChanged(
                $fresh,
                $previousStatus,
                $fresh->status->value,
                $actor,
            );
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $actor);
        } elseif ($fresh->getChanges() !== []) {
            $chg = collect($fresh->getChanges())->except(['updated_at'])->all();
            TaskActivityLogger::updated($fresh, $actor, $chg);
            NotificationDispatcher::taskUpdated($fresh, $actor, $chg);
        }

        $flash = match ($newStatus) {
            TaskStatus::InProgress->value => 'Đã bắt đầu làm — SLA giờ ước tính đang chạy.',
            TaskStatus::InReview->value => 'Đã chuyển sang chờ duyệt.',
            TaskStatus::Done->value => 'Đã hoàn thành — tiến độ 100%.',
            TaskStatus::Blocked->value => 'Đã đánh dấu bị chặn.',
            TaskStatus::Todo->value => 'Đã chuyển về cần làm.',
            default => 'Đã cập nhật trạng thái.',
        };

        return ['task' => $fresh, 'flash' => $flash];
    }
}
