<?php

namespace App\Application\Task;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskTimeliness;
use Illuminate\Support\Facades\DB;

class BulkCreateTasksUseCase
{
    /**
     * @param  array{rows: array<int, array{title: string}>, defaults?: array<string, mixed>}  $data
     * @return int Number of tasks created
     */
    public function execute(Project $project, array $data, SystemAccount $actor): int
    {
        $defaults = $data['defaults'] ?? [];
        $assigneeIds = $defaults['assignee_ids'] ?? [];
        $orderBase = (int) $project->tasks()->max('order_column');
        $created = 0;

        DB::transaction(function () use ($data, $defaults, $assigneeIds, $project, $actor, $orderBase, &$created) {
            foreach ($data['rows'] as $i => $row) {
                $payload = [
                    'title' => $row['title'],
                    'status' => $defaults['status'] ?? TaskStatus::Todo->value,
                    'priority' => $defaults['priority'] ?? TaskPriority::Medium->value,
                    'phase' => $defaults['phase'] ?? TaskPhase::Development->value,
                    'sprint_id' => $defaults['sprint_id'] ?? null,
                    'assignee_id' => $defaults['assignee_id'] ?? null,
                    'reviewer_id' => $defaults['reviewer_id'] ?? null,
                    'reporter_id' => $defaults['reporter_id'] ?? $actor->employee_id,
                    'progress' => 0,
                    'order_column' => $orderBase + $i + 1,
                ];

                $task = $project->tasks()->create($payload);

                if ($assigneeIds !== []) {
                    $task->assignees()->sync($assigneeIds);
                }

                $fresh = $task->fresh();
                TaskTimeliness::syncWorkStartedAt($fresh);
                TaskActivityLogger::created($fresh, $actor);
                NotificationDispatcher::taskCreated($fresh->load('project'), $actor);
                $created++;
            }
        });

        return $created;
    }
}
