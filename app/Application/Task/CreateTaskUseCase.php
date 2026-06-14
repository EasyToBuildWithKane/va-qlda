<?php

namespace App\Application\Task;

use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Services\TaskEmailService;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskProgress;
use App\Support\TaskTimeliness;

class CreateTaskUseCase
{
    public function __construct(private readonly TaskEmailService $taskEmail) {}

    /**
     * @param  array<string, mixed>  $data  Validated payload from StoreTaskRequest
     */
    public function execute(Project $project, array $data, SystemAccount $actor): Task
    {
        $dependencies = $data['dependencies'] ?? [];
        $assigneeIds = $data['assignee_ids'] ?? [];
        unset($data['dependencies'], $data['assignee_ids']);
        unset($data['progress']);
        $data['progress'] = TaskProgress::fromStatus($data['status']);

        $task = $project->tasks()->create([
            ...$data,
            'reporter_id' => $data['reporter_id'] ?? $actor->employee_id,
            'order_column' => (int) $project->tasks()->max('order_column') + 1,
        ]);

        if ($dependencies !== []) {
            $task->dependencies()->sync($dependencies);
        }
        if ($assigneeIds !== []) {
            $task->assignees()->sync($assigneeIds);
        }

        $fresh = $task->fresh()->load(['project', 'sprint', 'assignees', 'assignee']);
        TaskTimeliness::syncWorkStartedAt($fresh);
        TaskActivityLogger::created($fresh, $actor);
        NotificationDispatcher::taskCreated($fresh, $actor);

        $this->queueAssignmentEmails($fresh);

        return $fresh;
    }

    private function queueAssignmentEmails(Task $task): void
    {
        if (! $this->taskEmail->notifyOnAssignEnabled()) {
            return;
        }

        $employeeIds = collect([$task->assignee_id])
            ->merge($task->assignees->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::query()->find($employeeId);
            if ($employee instanceof Employee) {
                $this->taskEmail->queueTaskAssigned($task, $employee);
            }
        }
    }
}
