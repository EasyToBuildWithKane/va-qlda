<?php

namespace App\Application\Task;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskTimeliness;

class CreateTaskUseCase
{
    /**
     * @param  array<string, mixed>  $data  Validated payload from StoreTaskRequest
     */
    public function execute(Project $project, array $data, SystemAccount $actor): Task
    {
        $dependencies = $data['dependencies'] ?? [];
        $assigneeIds = $data['assignee_ids'] ?? [];
        unset($data['dependencies'], $data['assignee_ids']);

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

        $fresh = $task->fresh();
        TaskTimeliness::syncWorkStartedAt($fresh);
        TaskActivityLogger::created($fresh, $actor);
        NotificationDispatcher::taskCreated($fresh->load('project'), $actor);

        return $fresh;
    }
}
