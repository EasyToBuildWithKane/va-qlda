<?php

namespace App\Application\RoutineTask;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Domain\RoutineTask\Support\RoutineTaskSchedule;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Facades\DB;

class ToggleRoutineTaskStatusUseCase
{
    /**
     * Cycle todo → in_progress → done → todo and mirror status into today's draft report JSON.
     */
    public function execute(RoutineTask $task, ?TaskStatus $explicit = null): RoutineTask
    {
        return DB::transaction(function () use ($task, $explicit) {
            $next = $explicit ?? $this->nextStatus($task->status);

            if (! in_array($next->value, RoutineTask::allowedStatusValues(), true)) {
                $next = TaskStatus::Todo;
            }

            $task->forceFill([
                'status' => $next,
                'progress_percent' => RoutineTaskSchedule::progressFor(
                    $next,
                    null,
                    (int) $task->progress_percent,
                ),
                'completed_at' => $next === TaskStatus::Done ? now() : null,
            ])->save();

            $this->syncStatusIntoTodayReport($task->fresh());

            return $task->refresh();
        });
    }

    private function nextStatus(TaskStatus $current): TaskStatus
    {
        return match ($current) {
            TaskStatus::Todo => TaskStatus::InProgress,
            TaskStatus::InProgress => TaskStatus::Done,
            default => TaskStatus::Todo,
        };
    }

    /**
     * Update projects[].tasks[].status for ROUTINE_PROJECT_ID on today's editable report.
     */
    private function syncStatusIntoTodayReport(RoutineTask $task): void
    {
        $report = DailyReport::query()
            ->where('employee_id', $task->employee_id)
            ->whereDate('date', DailyReportCalendar::today())
            ->where('status', ReportStatus::Draft->value)
            ->lockForUpdate()
            ->first();

        if ($report === null) {
            return;
        }

        $projects = $report->projects;
        if (! is_array($projects) || $projects === []) {
            return;
        }

        $changed = false;
        $statusValue = $task->status->value;

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

                if ((string) ($taskRef['id'] ?? '') !== (string) $task->id) {
                    continue;
                }

                if (($taskRef['status'] ?? null) === $statusValue) {
                    continue;
                }

                $projects[$projectIndex]['tasks'][$taskIndex]['status'] = $statusValue;
                $changed = true;
            }
        }

        if ($changed) {
            $report->forceFill(['projects' => $projects])->save();
        }
    }
}
