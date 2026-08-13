<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Models\SystemAccount;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Str;

/**
 * Persist routine (sentinel project id = -1) tasks from the report's `projects`
 * JSON into `routine_tasks` and replace placeholder / local keys with real UUIDs.
 */
class SyncDailyReportRoutineTasksUseCase
{
    public function execute(DailyReport $report, SystemAccount $actor): DailyReport
    {
        $employeeId = (int) ($actor->employee_id ?? $report->employee_id ?? 0);
        if ($employeeId <= 0) {
            return $report;
        }

        $projects = $report->projects;
        if (! is_array($projects) || $projects === []) {
            return $report;
        }

        $changed = false;

        foreach ($projects as $projectIndex => $project) {
            if (! is_array($project)) {
                continue;
            }

            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId !== ReportProjectSync::ROUTINE_PROJECT_ID) {
                continue;
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

                $rawId = isset($taskRef['id']) ? (string) $taskRef['id'] : '';
                $localKey = isset($taskRef['_localKey']) ? trim((string) $taskRef['_localKey']) : '';
                $inlineStatus = TaskStatus::tryFrom((string) ($taskRef['status'] ?? '')) ?? TaskStatus::Todo;
                if (! in_array($inlineStatus->value, RoutineTask::allowedStatusValues(), true)) {
                    $inlineStatus = TaskStatus::Todo;
                }

                if ($this->isUuid($rawId)) {
                    $existing = RoutineTask::query()
                        ->whereKey($rawId)
                        ->forEmployee($employeeId)
                        ->first();

                    if ($existing !== null) {
                        // Keep title in sync when edited from the report form.
                        if ($existing->title !== $title) {
                            $existing->forceFill(['title' => $title])->save();
                        }

                        $nextRef = [
                            ...$taskRef,
                            'id' => $existing->id,
                            'status' => $existing->status->value,
                        ];

                        if ($nextRef !== $taskRef) {
                            $projects[$projectIndex]['tasks'][$taskIndex] = $nextRef;
                            $changed = true;
                        }

                        continue;
                    }
                }

                if ($localKey !== '' && ! $this->isUuid($rawId)) {
                    $linkedId = $this->resolveIdByLocalKey($tasks, $localKey);
                    if ($linkedId !== null) {
                        $linked = RoutineTask::query()->find($linkedId);
                        $projects[$projectIndex]['tasks'][$taskIndex] = [
                            ...$taskRef,
                            'id' => $linkedId,
                            'status' => $linked?->status->value ?? $inlineStatus->value,
                        ];
                        $changed = true;

                        continue;
                    }
                }

                $existingByTitle = RoutineTask::query()
                    ->forEmployee($employeeId)
                    ->incomplete()
                    ->where('title', $title)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($existingByTitle !== null) {
                    $projects[$projectIndex]['tasks'][$taskIndex] = [
                        ...$taskRef,
                        'id' => $existingByTitle->id,
                        'status' => $existingByTitle->status->value,
                    ];
                    $changed = true;

                    continue;
                }

                $nextPosition = (int) RoutineTask::query()
                    ->forEmployee($employeeId)
                    ->max('position') + 1;

                $created = RoutineTask::query()->create([
                    'employee_id' => $employeeId,
                    'title' => $title,
                    'description' => null,
                    'status' => TaskStatus::Todo,
                    'position' => $nextPosition,
                    'completed_at' => null,
                ]);

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

    private function isUuid(string $value): bool
    {
        return $value !== '' && Str::isUuid($value);
    }

    /**
     * @param  array<int, mixed>  $tasks
     */
    private function resolveIdByLocalKey(array $tasks, string $localKey): ?string
    {
        foreach ($tasks as $taskRef) {
            if (! is_array($taskRef)) {
                continue;
            }
            if (($taskRef['_localKey'] ?? null) !== $localKey) {
                continue;
            }
            $id = isset($taskRef['id']) ? (string) $taskRef['id'] : '';
            if ($this->isUuid($id)) {
                return $id;
            }
        }

        return null;
    }
}
