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
    use Concerns\AppliesTaskStatusRules;

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
        $validated = $this->applyStatusTransitionRules($task, $validated, $actor);
        $task->update($validated);
        $changes = collect($task->getChanges())->except(['updated_at'])->all();
        $fresh = $task->fresh();

        if (array_key_exists('assignee_id', $validated)) {
            $assigneeId = $validated['assignee_id'];
            $fresh->assignees()->sync($assigneeId ? [(int) $assigneeId] : []);
        }

        TaskTimeliness::syncWorkStartedAt($fresh, $previousStatus);

        if (isset($changes['status'])) {
            $this->logStatusSideEffects($fresh, $previousStatus, $actor, true);
            NotificationDispatcher::taskStatusChanged($fresh, $previousStatus, $fresh->status->value, $actor);
        } elseif ($changes !== []) {
            TaskActivityLogger::updated($fresh, $actor, $changes);
            NotificationDispatcher::taskUpdated($fresh, $actor, $changes);
        }

        $flash = isset($validated['status'])
            ? match ($newStatus) {
                TaskStatus::InProgress->value => 'Đã bắt đầu làm — SLA giờ ước tính đang chạy.',
                TaskStatus::InReview->value => 'Đã chuyển sang chờ duyệt.',
                TaskStatus::Done->value => 'Đã hoàn thành — tiến độ 100%.',
                TaskStatus::Blocked->value => 'Đã đánh dấu bị chặn.',
                TaskStatus::Todo->value => 'Đã chuyển về cần làm.',
                default => 'Đã cập nhật trạng thái.',
            }
        : 'Đã cập nhật công việc.';

        return ['task' => $fresh, 'flash' => $flash];
    }
}
