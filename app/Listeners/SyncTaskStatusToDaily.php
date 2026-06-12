<?php

namespace App\Listeners;

use App\Domain\DailyReport\Models\DailyReport;
use App\Events\TaskStatusChanged;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncTaskStatusToDaily
{
    /**
     * Mirror sprint "done" into the assignee's daily report `task_status_snapshot`.
     *
     * Report day is resolved in the daily-report timezone (Asia/Ho_Chi_Minh by default),
     * not from `$task->updated_at` (UTC), so late-evening VN completions still match
     * the correct calendar report.
     *
     * Failures are logged and swallowed so marking a task done on the sprint board
     * never returns 500 when snapshot sync degrades.
     */
    public function handle(TaskStatusChanged $event): void
    {
        $task = $event->task;

        if ($task->status !== TaskStatus::Done) {
            return;
        }

        if ($task->assignee_id === null) {
            return;
        }

        try {
            DB::transaction(function () use ($task): void {
                $targetDate = DailyReportCalendar::today();

                $report = DailyReport::query()
                    ->where('employee_id', $task->assignee_id)
                    ->whereDate('date', $targetDate)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    return;
                }

                /** @var array<int, array<string, mixed>> $snapshot */
                $snapshot = $report->task_status_snapshot ?? [];
                if (! is_array($snapshot)) {
                    $snapshot = [];
                }

                $entry = [
                    'task_id' => $task->id,
                    'status' => TaskStatus::Done->value,
                    'done_at' => now()->toIso8601String(),
                ];

                if (in_array($report->status, [ReportStatus::Submitted, ReportStatus::Reviewed], true)) {
                    $entry['synced_after_submit'] = true;
                }

                $updated = false;
                foreach ($snapshot as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if ((int) ($item['task_id'] ?? 0) === (int) $task->id) {
                        $snapshot[$index] = array_merge($item, $entry);
                        $updated = true;

                        break;
                    }
                }

                if (! $updated) {
                    $snapshot[] = $entry;
                }

                $report->forceFill([
                    'task_status_snapshot' => array_values($snapshot),
                ])->save();
            });
        } catch (\Throwable $e) {
            Log::error('SyncTaskStatusToDaily failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
