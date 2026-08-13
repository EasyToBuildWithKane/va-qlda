<?php

namespace App\Console\Commands;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Domain\RoutineTask\Models\RoutineTask;
use App\Support\Enums\TaskStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfill routine_tasks from historical daily-report sentinel blocks (id = -1)
 * and remap projects JSON task ids to real UUIDs.
 */
class BackfillRoutineTasksCommand extends Command
{
    protected $signature = 'routine-tasks:backfill
                            {--dry-run : Chỉ thống kê, không ghi DB}';

    protected $description = 'Tạo routine_tasks từ projects JSON sentinel (-1) của báo cáo ngày cũ';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $remapped = 0;
        $reportsTouched = 0;

        DailyReport::query()
            ->whereNotNull('projects')
            ->orderBy('id')
            ->chunkById(100, function ($reports) use ($dryRun, &$created, &$remapped, &$reportsTouched) {
                foreach ($reports as $report) {
                    $projects = $report->projects;
                    if (! is_array($projects) || $projects === []) {
                        continue;
                    }

                    $changed = false;
                    $employeeId = (int) $report->employee_id;

                    foreach ($projects as $projectIndex => $project) {
                        if (! is_array($project)) {
                            continue;
                        }

                        if ((int) ($project['id'] ?? 0) !== ReportProjectSync::ROUTINE_PROJECT_ID) {
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
                            if ($rawId !== '' && Str::isUuid($rawId)) {
                                $exists = RoutineTask::query()
                                    ->whereKey($rawId)
                                    ->forEmployee($employeeId)
                                    ->exists();
                                if ($exists) {
                                    continue;
                                }
                            }

                            $status = TaskStatus::tryFrom((string) ($taskRef['status'] ?? '')) ?? TaskStatus::Todo;
                            if (! in_array($status->value, RoutineTask::allowedStatusValues(), true)) {
                                $status = TaskStatus::Todo;
                            }

                            $match = RoutineTask::query()
                                ->forEmployee($employeeId)
                                ->where('title', $title)
                                ->orderByDesc('updated_at')
                                ->first();

                            if ($match === null) {
                                $created++;
                                if (! $dryRun) {
                                    $nextPosition = (int) RoutineTask::query()
                                        ->forEmployee($employeeId)
                                        ->max('position') + 1;

                                    $match = RoutineTask::query()->create([
                                        'employee_id' => $employeeId,
                                        'title' => $title,
                                        'description' => null,
                                        'status' => $status,
                                        'position' => $nextPosition,
                                        'completed_at' => $status === TaskStatus::Done ? ($report->date ?? now()) : null,
                                    ]);
                                } else {
                                    continue;
                                }
                            }

                            if ((string) ($taskRef['id'] ?? '') !== (string) $match->id
                                || ($taskRef['status'] ?? null) !== $match->status->value) {
                                $projects[$projectIndex]['tasks'][$taskIndex] = [
                                    ...$taskRef,
                                    'id' => $match->id,
                                    'status' => $match->status->value,
                                ];
                                $changed = true;
                                $remapped++;
                            }
                        }
                    }

                    if ($changed) {
                        $reportsTouched++;
                        if (! $dryRun) {
                            $report->forceFill(['projects' => $projects])->save();
                        }
                    }
                }
            });

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Created: {$created}, remapped refs: {$remapped}, reports updated: {$reportsTouched}");

        return self::SUCCESS;
    }
}
