<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\NotificationPriority;
use App\Support\Enums\NotificationType;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * @param  iterable<SystemAccount|int>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function notify(
        iterable $recipients,
        NotificationType $type,
        string $title,
        ?string $body = null,
        array $context = [],
    ): void {
        $priority = $context['priority'] ?? $type->defaultPriority();
        if (is_string($priority)) {
            $priority = NotificationPriority::from($priority);
        }

        $actor = $context['actor'] ?? null;
        $actorAccountId = $actor instanceof SystemAccount ? $actor->id : ($context['actor_account_id'] ?? null);
        $actorName = $context['actor_name'] ?? ($actor instanceof SystemAccount ? $actor->display_name : null);

        $rows = [];
        $now = now();

        foreach ($this->normalizeRecipients($recipients) as $account) {
            if (! $this->shouldDeliver($account, $type)) {
                continue;
            }

            $rows[] = [
                'recipient_account_id' => $account->id,
                'type' => $type->value,
                'category' => $type->category()->value,
                'priority' => $priority->value,
                'title' => Str::limit($title, 255, '…'),
                'body' => $body,
                'actor_account_id' => $actorAccountId,
                'actor_name' => $actorName,
                'project_id' => $context['project_id'] ?? null,
                'sprint_id' => $context['sprint_id'] ?? null,
                'task_id' => $context['task_id'] ?? null,
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => $context['entity_id'] ?? null,
                'action_url' => $context['action_url'] ?? null,
                'meta' => isset($context['meta']) ? json_encode($context['meta']) : null,
                'is_admin_feed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            AppNotification::insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int>  $exceptAccountIds  Đã nhận bản in-app (`is_admin_feed=false`) — tránh trùng inbox
     */
    public function notifyAdmins(
        NotificationType $type,
        string $title,
        ?string $body = null,
        array $context = [],
        array $exceptAccountIds = [],
    ): void {
        $priority = $context['priority'] ?? $type->defaultPriority();
        if (is_string($priority)) {
            $priority = NotificationPriority::from($priority);
        }

        $actor = $context['actor'] ?? null;
        $actorAccountId = $actor instanceof SystemAccount ? $actor->id : ($context['actor_account_id'] ?? null);
        $actorName = $context['actor_name'] ?? ($actor instanceof SystemAccount ? $actor->display_name : null);

        $adminIds = SystemAccount::query()
            ->where('is_active', true)
            ->whereIn('role', [SystemRole::SuperAdmin->value, SystemRole::Admin->value])
            ->pluck('id');

        $rows = [];
        $now = now();

        $except = array_flip($exceptAccountIds);

        foreach ($adminIds as $adminId) {
            if (isset($except[$adminId])) {
                continue;
            }

            $rows[] = [
                'recipient_account_id' => $adminId,
                'type' => $type->value,
                'category' => $type->category()->value,
                'priority' => $priority->value,
                'title' => Str::limit($title, 255, '…'),
                'body' => $body,
                'actor_account_id' => $actorAccountId,
                'actor_name' => $actorName,
                'project_id' => $context['project_id'] ?? null,
                'sprint_id' => $context['sprint_id'] ?? null,
                'task_id' => $context['task_id'] ?? null,
                'entity_type' => $context['entity_type'] ?? null,
                'entity_id' => $context['entity_id'] ?? null,
                'action_url' => $context['action_url'] ?? null,
                'meta' => isset($context['meta']) ? json_encode($context['meta']) : null,
                'is_admin_feed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            AppNotification::insert($rows);
        }
    }

    public function notifyTaskStakeholders(
        Task $task,
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?SystemAccount $actor = null,
        array $extra = [],
    ): void {
        $task->loadMissing(['project', 'watchers']);

        $employeeIds = collect([
            $task->assignee_id,
            $task->reporter_id,
            $task->reviewer_id,
        ])
            ->merge($task->watchers->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $recipients = $this->accountsForEmployees($employeeIds)
            ->reject(fn (SystemAccount $a) => $actor && $a->id === $actor->id);

        $context = array_merge([
            'actor' => $actor,
            'project_id' => $task->project_id,
            'sprint_id' => $task->sprint_id,
            'task_id' => $task->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action_url' => "/projects/{$task->project_id}?task={$task->id}",
            'meta' => [
                'task_title' => $task->title,
                'project_name' => $task->project?->name,
            ],
        ], $extra);

        $this->notify($recipients, $type, $title, $body, $context);

        if ($actor) {
            $taskRef = 'TASK-'.$task->id;
            $this->notifyAdmins(
                NotificationType::AdminUserAction,
                $title,
                $body ?? "{$taskRef}: {$task->title}",
                array_merge($context, [
                    'actor' => $actor,
                    'meta' => array_merge($context['meta'] ?? [], ['task_ref' => $taskRef]),
                ]),
            );
        }
    }

    public function taskRef(Task $task): string
    {
        return 'TASK-'.$task->id;
    }

    public function unreadCount(SystemAccount $account): int
    {
        return $this->queryForAccount($account)
            ->whereNull('read_at')
            ->count();
    }

    public function queryForAccount(SystemAccount $account): Builder
    {
        return AppNotification::query()
            ->where('recipient_account_id', $account->id)
            ->orderByDesc('created_at');
    }

    /**
     * @param  array<int>  $employeeIds
     * @return Collection<int, SystemAccount>
     */
    public function accountsForEmployees(array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return SystemAccount::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->get();
    }

    public function preferencesFor(SystemAccount $account): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['system_account_id' => $account->id],
            [
                'disabled_types' => [],
                'channel_in_app' => true,
                'channel_email' => false,
                'channel_push' => false,
            ],
        );
    }

    public function recordSystemEvent(
        SystemAccount $actor,
        NotificationType $type,
        string $title,
        ?string $body = null,
        ?Project $project = null,
    ): void {
        $context = [
            'actor' => $actor,
            'project_id' => $project?->id,
            'action_url' => $project ? "/projects/{$project->id}" : null,
        ];

        $this->notify([$actor], $type, $title, $body, $context);
        $this->notifyAdmins($type, $title, $body, $context);
    }

    /**
     * @param  iterable<SystemAccount|int>  $recipients
     * @return Collection<int, SystemAccount>
     */
    private function normalizeRecipients(iterable $recipients): Collection
    {
        $accounts = collect();

        foreach ($recipients as $item) {
            if ($item instanceof SystemAccount) {
                $accounts->push($item);
            } elseif (is_int($item)) {
                $found = SystemAccount::query()->where('id', $item)->where('is_active', true)->first();
                if ($found) {
                    $accounts->push($found);
                }
            }
        }

        return $accounts->unique('id')->values();
    }

    private function shouldDeliver(SystemAccount $account, NotificationType $type): bool
    {
        return $this->preferencesFor($account)->isTypeEnabled($type->value);
    }
}
