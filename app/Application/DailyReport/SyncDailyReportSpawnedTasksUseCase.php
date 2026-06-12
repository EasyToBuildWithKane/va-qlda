<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskSource;
use App\Support\Enums\TaskStatus;
use App\Support\TaskActivityLogger;
use App\Support\TaskProgress;
use Illuminate\Support\Facades\Gate;

class SyncDailyReportSpawnedTasksUseCase
{
    /**
     * Persist ad-hoc tasks from the report's `projects` JSON into `tasks`
     * (source = daily, daily_report_id set) and replace placeholder ids.
     *
     * @throws DailyReportException when the actor cannot contribute to a linked project
     */
    public function execute(DailyReport $report, SystemAccount $actor): DailyReport
    {
        $projects = $report->projects;
        if (! is_array($projects) || $projects === []) {
            return $report;
        }

        $changed = false;

        foreach ($projects as $projectIndex => $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $projectModel = Project::query()->find($projectId);
            if ($projectModel === null) {
                continue;
            }

            if (! Gate::forUser($actor)->allows('contribute', $projectModel)) {
                throw DailyReportException::projectNotAllowed($projectModel->name);
            }

            $tasks = $project['tasks'] ?? [];
            if (! is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $taskIndex => $taskRef) {
                if (! is_array($taskRef)) {
                    continue;
                }

                $title = trim((string) ($taskRef['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $taskId = isset($taskRef['id']) ? (int) $taskRef['id'] : 0;
                $localKey = isset($taskRef['_localKey']) ? trim((string) $taskRef['_localKey']) : '';

                if ($taskId > 0) {
                    $exists = Task::query()
                        ->whereKey($taskId)
                        ->where('project_id', $projectId)
                        ->exists();
                    if ($exists) {
                        continue;
                    }
                }

                if ($localKey !== '' && $taskId <= 0) {
                    $linkedId = $this->resolveSpawnIdByLocalKey($projects, $projectIndex, $localKey);
                    if ($linkedId !== null) {
                        $linked = Task::query()->find($linkedId);
                        $projects[$projectIndex]['tasks'][$taskIndex] = [
                            ...$taskRef,
                            'id' => $linkedId,
                            'status' => $linked?->status->value ?? TaskStatus::Todo->value,
                        ];
                        $changed = true;

                        continue;
                    }
                }

                $existingSpawn = Task::query()
                    ->where('daily_report_id', $report->id)
                    ->where('project_id', $projectId)
                    ->where('source', TaskSource::Daily)
                    ->where('title', $title)
                    ->first();

                if ($existingSpawn !== null) {
                    $projects[$projectIndex]['tasks'][$taskIndex] = [
                        ...$taskRef,
                        'id' => $existingSpawn->id,
                        'status' => $existingSpawn->status->value,
                    ];
                    $changed = true;

                    continue;
                }

                $nextOrder = (int) Task::query()->where('project_id', $projectId)->max('order_column') + 1;

                $created = Task::query()->create([
                    'project_id' => $projectId,
                    'title' => $title,
                    'status' => TaskStatus::Todo,
                    'priority' => TaskPriority::Medium,
                    'assignee_id' => $actor->employee_id,
                    'reporter_id' => $actor->employee_id,
                    'source' => TaskSource::Daily,
                    'daily_report_id' => $report->id,
                    'order_column' => $nextOrder,
                    'progress' => TaskProgress::fromStatus(TaskStatus::Todo),
                ]);

                TaskActivityLogger::created($created, $actor);

                $projects[$projectIndex]['tasks'][$taskIndex] = [
                    ...$taskRef,
                    'id' => $created->id,
                    'status' => TaskStatus::Todo->value,
                ];
                $changed = true;
            }
        }

        if ($changed) {
            $report->forceFill(['projects' => $projects])->save();
        }

        return $report->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     */
    private function resolveSpawnIdByLocalKey(array $projects, int $projectIndex, string $localKey): ?int
    {
        $tasks = $projects[$projectIndex]['tasks'] ?? [];
        if (! is_array($tasks)) {
            return null;
        }

        foreach ($tasks as $taskRef) {
            if (! is_array($taskRef)) {
                continue;
            }
            if (($taskRef['_localKey'] ?? null) !== $localKey) {
                continue;
            }
            $id = (int) ($taskRef['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }
}
