<?php

namespace App\Domain\DailyReport\Support;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Models\Task;

/**
 * Freeze live task / routine-task statuses into the report's `projects` JSON at submit time.
 */
final class ReportProjectTaskStatus
{
    public static function freezeIntoReport(DailyReport $report): void
    {
        $projects = $report->projects;
        if (! is_array($projects) || $projects === []) {
            return;
        }

        $changed = false;

        foreach ($projects as $projectIndex => $project) {
            if (! is_array($project)) {
                continue;
            }

            $projectId = (int) ($project['id'] ?? 0);
            $tasks = $project['tasks'] ?? [];
            if (! is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $taskIndex => $taskRef) {
                if (! is_array($taskRef)) {
                    continue;
                }

                $status = null;

                if (ReportProjectSync::isLinkableProjectId($projectId)) {
                    $taskId = (int) ($taskRef['id'] ?? 0);
                    if ($taskId <= 0) {
                        continue;
                    }

                    $task = Task::query()->find($taskId);
                    if ($task === null) {
                        continue;
                    }

                    $status = $task->status->value;
                } elseif ($projectId === ReportProjectSync::ROUTINE_PROJECT_ID) {
                    $rawId = isset($taskRef['id']) ? (string) $taskRef['id'] : '';
                    if ($rawId === '') {
                        continue;
                    }

                    $routine = RoutineTask::query()->find($rawId);
                    if ($routine === null) {
                        continue;
                    }

                    $status = $routine->status->value;
                } else {
                    continue;
                }

                if (($taskRef['status'] ?? null) === $status) {
                    continue;
                }

                $projects[$projectIndex]['tasks'][$taskIndex]['status'] = $status;
                $changed = true;
            }
        }

        if ($changed) {
            $report->forceFill(['projects' => $projects])->save();
        }
    }
}
