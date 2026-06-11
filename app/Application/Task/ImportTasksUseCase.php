<?php

namespace App\Application\Task;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskProgress;
use App\Support\TaskTimeliness;
use Illuminate\Support\Facades\DB;

class ImportTasksUseCase
{
    /**
     * @param  array{rows: array<int, array<string, mixed>>}  $data
     */
    public function execute(Project $project, array $data, SystemAccount $actor): int
    {
        $orderBase = (int) $project->tasks()->max('order_column');
        $created = 0;

        DB::transaction(function () use ($data, $project, $actor, $orderBase, &$created) {
            foreach ($data['rows'] as $i => $row) {
                $task = $project->tasks()->create([
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'sprint_id' => $row['sprint_id'] ?? null,
                    'status' => $row['status'] ?? TaskStatus::Todo->value,
                    'priority' => $row['priority'] ?? TaskPriority::Medium->value,
                    'phase' => $row['phase'] ?? null,
                    'assignee_id' => $row['assignee_id'] ?? null,
                    'reviewer_id' => $row['reviewer_id'] ?? null,
                    'reporter_id' => $actor->employee_id,
                    'start_date' => $row['start_date'] ?? null,
                    'due_date' => $row['due_date'] ?? null,
                    'estimate_hours' => $row['estimate_hours'] ?? null,
                    'progress' => TaskProgress::fromStatus($row['status'] ?? TaskStatus::Todo->value),
                    'order_column' => $orderBase + $i + 1,
                ]);

                // Mirror the singular assignee into the canonical many-to-many pivot
                // so imported tasks behave like ones created via Create/Bulk/Update use cases.
                if (! empty($row['assignee_id'])) {
                    $task->assignees()->sync([$row['assignee_id']]);
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
