<?php

namespace App\Support;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Services\NotificationService;
use App\Support\Enums\NotificationType;

/**
 * Bridges domain activity loggers/controllers to the notification inbox.
 */
class NotificationDispatcher
{
    public static function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    public static function taskCreated(Task $task, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $title = $actor
            ? "{$actor->display_name} tạo {$ref}"
            : "Công việc mới {$ref}";

        $svc->notifyTaskStakeholders(
            $task,
            NotificationType::TaskCreated,
            $title,
            $task->title,
            $actor,
        );

        if ($task->assignee_id && $actor) {
            $assignee = $svc->accountsForEmployees([$task->assignee_id])->first();
            if ($assignee && $assignee->id !== $actor->id) {
                $svc->notify(
                    [$assignee],
                    NotificationType::TaskAssigned,
                    "Bạn được giao {$ref}",
                    $task->title,
                    self::taskContext($task, $actor),
                );
            }
        }
    }

    public static function taskUpdated(Task $task, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $svc = self::service();
        $ref = $svc->taskRef($task);

        if (isset($changes['due_date'])) {
            $svc->notifyTaskStakeholders(
                $task,
                NotificationType::TaskDeadlineChanged,
                "{$ref} — thay đổi hạn",
                'Hạn mới: '.($task->due_date?->format('d/m/Y') ?? '—'),
                $actor,
            );

            return;
        }

        if (isset($changes['assignee_id'])) {
            $assignee = $svc->accountsForEmployees([(int) $changes['assignee_id']])->first();
            if ($assignee) {
                $svc->notify(
                    [$assignee],
                    NotificationType::TaskAssigned,
                    "Bạn được giao {$ref}",
                    $task->title,
                    self::taskContext($task, $actor),
                );
            }
        }

        $svc->notifyTaskStakeholders(
            $task,
            NotificationType::TaskUpdated,
            $actor ? "{$actor->display_name} cập nhật {$ref}" : "Cập nhật {$ref}",
            $task->title,
            $actor,
        );
    }

    public static function taskStatusChanged(Task $task, string $from, string $to, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $type = $to === 'done' ? NotificationType::TaskCompleted : NotificationType::TaskStatusChanged;
        $title = $actor
            ? "{$actor->display_name} đổi trạng thái {$ref}"
            : "Thay đổi trạng thái {$ref}";

        $svc->notifyTaskStakeholders(
            $task,
            $type,
            $title,
            "{$from} → {$to}",
            $actor,
        );
    }

    public static function taskComment(Task $task, ?SystemAccount $actor, bool $isMention = false): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $type = $isMention ? NotificationType::CommentMention : NotificationType::CommentTaskThread;

        $svc->notifyTaskStakeholders(
            $task,
            $type,
            $actor ? "{$actor->display_name} — bình luận {$ref}" : "Bình luận mới {$ref}",
            null,
            $actor,
        );
    }

    public static function projectUpdated(Project $project, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $svc = self::service();
        $employeeIds = $project->members()->pluck('employees.id')->all();
        $members = $svc->accountsForEmployees($employeeIds);

        $type = match (true) {
            isset($changes['project_manager_id']) => NotificationType::ProjectPmChanged,
            isset($changes['end_date']) => NotificationType::ProjectDeadlineChanged,
            isset($changes['status']) => NotificationType::ProjectStatusChanged,
            default => NotificationType::ProjectStatusChanged,
        };

        $title = $actor
            ? "{$actor->display_name} cập nhật dự án {$project->name}"
            : "Dự án {$project->name} được cập nhật";

        $svc->notify($members, $type, $title, null, [
            'actor' => $actor,
            'project_id' => $project->id,
            'action_url' => "/projects/{$project->id}",
        ]);

        if ($actor) {
            $svc->notifyAdmins(NotificationType::AdminUserAction, $title, null, [
                'actor' => $actor,
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }
    }

    /** @return array<string, mixed> */
    private static function taskContext(Task $task, ?SystemAccount $actor): array
    {
        return [
            'actor' => $actor,
            'project_id' => $task->project_id,
            'sprint_id' => $task->sprint_id,
            'task_id' => $task->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action_url' => "/projects/{$task->project_id}?task={$task->id}",
            'meta' => ['task_ref' => 'TASK-'.$task->id, 'task_title' => $task->title],
        ];
    }
}
