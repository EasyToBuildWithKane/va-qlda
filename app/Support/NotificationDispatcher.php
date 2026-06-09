<?php

namespace App\Support;

use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Sprint;
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
            $detail = 'Hạn mới: '.($task->due_date?->format('d/m/Y') ?? '—');
            $svc->notifyTaskStakeholders(
                $task,
                NotificationType::TaskDeadlineChanged,
                "{$ref} — thay đổi hạn",
                self::taskNotificationBody($task, $detail),
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

        $summary = NotificationChangeSummary::task($changes);
        $body = self::taskNotificationBody($task, $summary);

        $svc->notifyTaskStakeholders(
            $task,
            NotificationType::TaskUpdated,
            $actor ? "{$actor->display_name} cập nhật {$ref}" : "Cập nhật {$ref}",
            $body,
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

        $body = self::taskNotificationBody($task, "Trạng thái: {$from} → {$to}");

        $svc->notifyTaskStakeholders(
            $task,
            $type,
            $title,
            $body,
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

    public static function projectCreated(Project $project, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $title = $actor
            ? "{$actor->display_name} tạo dự án {$project->name}"
            : "Dự án mới: {$project->name}";

        if ($actor) {
            $svc->notifyAdmins(NotificationType::ProjectCreated, $title, $project->code, [
                'actor' => $actor,
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }
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
            isset($changes['manager_id']) => NotificationType::ProjectPmChanged,
            isset($changes['due_date']) => NotificationType::ProjectDeadlineChanged,
            isset($changes['status']) => NotificationType::ProjectStatusChanged,
            default => NotificationType::ProjectStatusChanged,
        };

        $title = $actor
            ? "{$actor->display_name} cập nhật dự án {$project->name}"
            : "Dự án {$project->name} được cập nhật";

        $body = NotificationChangeSummary::project($changes);

        $svc->notify($members, $type, $title, $body, [
            'actor' => $actor,
            'project_id' => $project->id,
            'action_url' => "/projects/{$project->id}",
        ]);

        if ($actor) {
            $svc->notifyAdmins(NotificationType::AdminUserAction, $title, $body, [
                'actor' => $actor,
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }
    }

    public static function sprintChanged(
        Project $project,
        Sprint $sprint,
        string $verb,
        ?SystemAccount $actor,
    ): void {
        $svc = self::service();
        $members = $svc->accountsForEmployees($project->members()->pluck('employees.id')->all());
        $title = $actor
            ? "{$actor->display_name} {$verb} sprint {$sprint->name}"
            : "Sprint {$sprint->name} — {$verb}";

        $type = match ($verb) {
            'tạo' => NotificationType::SprintCreated,
            'cập nhật' => NotificationType::SprintStarted,
            default => NotificationType::SprintEnded,
        };

        $svc->notify($members, $type, $title, $project->name, [
            'actor' => $actor,
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'action_url' => "/projects/{$project->id}?tab=sprint",
        ]);
    }

    public static function blockerUpdated(Blocker $blocker, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === [] || ! $blocker->project_id) {
            return;
        }

        $svc = self::service();
        $blocker->loadMissing('project');
        $employeeIds = collect([$blocker->owner_id, $blocker->raised_by_id])
            ->merge($blocker->project?->members()->pluck('employees.id') ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $members = $svc->accountsForEmployees($employeeIds)
            ->reject(fn (SystemAccount $a) => $actor && $a->id === $actor->id);

        $ref = $blocker->code ?? ('RSK-'.$blocker->id);
        $title = $actor
            ? "{$actor->display_name} cập nhật {$ref}"
            : "Cập nhật {$ref}";

        $body = trim(($blocker->title ? $blocker->title."\n" : '').(NotificationChangeSummary::blocker($changes) ?? ''));

        $svc->notify($members, NotificationType::ProjectStatusChanged, $title, $body !== '' ? $body : null, [
            'actor' => $actor,
            'project_id' => $blocker->project_id,
            'entity_type' => 'blocker',
            'entity_id' => $blocker->id,
            'action_url' => '/blockers',
        ]);
    }

    public static function bugChanged(Bug $bug, string $verb, ?SystemAccount $actor, ?array $changes = null): void
    {
        $svc = self::service();
        $ref = $bug->code ?? ('BUG-'.$bug->id);
        $title = $actor
            ? "{$actor->display_name} {$verb} {$ref}"
            : "{$verb} {$ref}";

        $body = $changes ? NotificationChangeSummary::bug($changes) : $bug->title;

        $recipients = $svc->accountsForEmployees(array_filter([$bug->assignee_id, $bug->reporter_employee_id]));

        $svc->notify($recipients, NotificationType::TaskUpdated, $title, $body, [
            'actor' => $actor,
            'project_id' => $bug->project_id,
            'action_url' => "/bugs/{$bug->id}",
        ]);
    }

    public static function feedbackChanged(Feedback $feedback, string $verb, ?SystemAccount $actor, ?array $changes = null): void
    {
        $svc = self::service();
        $ref = $feedback->code ?? ('FB-'.$feedback->id);
        $title = $actor
            ? "{$actor->display_name} {$verb} {$ref}"
            : "{$verb} {$ref}";

        $body = $changes ? NotificationChangeSummary::feedback($changes) : $feedback->title;

        $recipients = $svc->accountsForEmployees(array_filter([$feedback->assignee_id, $feedback->reporter_employee_id]));

        $svc->notify($recipients, NotificationType::CommentReply, $title, $body, [
            'actor' => $actor,
            'project_id' => $feedback->project_id,
            'action_url' => $feedback->project_id
                ? "/projects/{$feedback->project_id}?tab=feedback"
                : "/feedback/{$feedback->id}",
        ]);
    }

    private static function taskNotificationBody(Task $task, ?string $detail = null): string
    {
        $lines = array_filter([
            trim((string) $task->title),
            $detail !== null && $detail !== '' ? trim($detail) : null,
        ]);

        return $lines !== [] ? implode("\n", $lines) : '—';
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
