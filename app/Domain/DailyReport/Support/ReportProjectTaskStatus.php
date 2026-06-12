<?php

namespace App\Domain\DailyReport\Support;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\Task;

/**
 * Freeze live sprint task statuses into the report's `projects` JSON at submit time.
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
            $tasks = $project['tasks'] ?? [];
            if (! is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $taskIndex => $taskRef) {
                if (! is_array($taskRef)) {
                    continue;
                }

                $taskId = (int) ($taskRef['id'] ?? 0);
                if ($taskId <= 0) {
                    continue;
                }

                $task = Task::query()->find($taskId);
                if ($task === null) {
                    continue;
                }

                $status = $task->status->value;
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
