<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\NotificationService;
use App\Support\Enums\NotificationType;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Console\Command;

/**
 * Scans for overdue / due-soon tasks, behind sprints, and overdue projects.
 * Schedule: hourly in production (app/Console/Kernel.php).
 */
class ScanNotificationAlerts extends Command
{
    protected $signature = 'notifications:scan-alerts';

    protected $description = 'Phát hiện task/sprint/dự án quá hạn và gửi thông báo';

    public function handle(NotificationService $notifications): int
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(2)->endOfDay();

        // ── Task overdue ──────────────────────────────────────────────────
        $overdue = Task::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->with('project')
            ->limit(100)
            ->get();

        foreach ($overdue as $task) {
            $notifications->notifyTaskStakeholders(
                $task,
                NotificationType::TaskOverdue,
                'TASK-'.$task->id.' quá hạn',
                'Hạn: '.$task->due_date?->format('d/m/Y'),
                null,
                ['priority' => 'critical'],
            );
        }

        // ── Task due soon ─────────────────────────────────────────────────
        $dueSoon = Task::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $soon])
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->with('project')
            ->limit(100)
            ->get();

        foreach ($dueSoon as $task) {
            $notifications->notifyTaskStakeholders(
                $task,
                NotificationType::TaskDueSoon,
                'TASK-'.$task->id.' sắp đến hạn',
                'Hạn: '.$task->due_date?->format('d/m/Y'),
                null,
            );
        }

        // ── Sprint behind (active, end_date đã qua, còn task chưa xong) ──
        $sprintsBehind = Sprint::query()
            ->where('status', SprintStatus::Active->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->with('project')
            ->limit(50)
            ->get();

        foreach ($sprintsBehind as $sprint) {
            $project = $sprint->project;
            if (! $project) {
                continue;
            }

            $openTasks = $project->tasks()
                ->where('sprint_id', $sprint->id)
                ->whereNotIn('status', [TaskStatus::Done->value])
                ->count();

            if ($openTasks === 0) {
                continue;
            }

            $employeeIds = $project->members()->pluck('employees.id')->all();
            $members = $notifications->accountsForEmployees($employeeIds);
            $title = "Sprint {$sprint->name} trễ tiến độ ({$openTasks} task chưa xong)";
            $body = "Dự án: {$project->name} · Hết: ".($sprint->end_date?->format('d/m/Y') ?? '—');

            $notifications->notify($members, NotificationType::SprintBehind, $title, $body, [
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'action_url' => "/projects/{$project->id}?tab=sprints",
            ]);
        }

        // ── Project overdue (active, due_date đã qua) ─────────────────────
        $projectsOverdue = Project::query()
            ->where('status', ProjectStatus::Active->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->limit(50)
            ->get();

        foreach ($projectsOverdue as $project) {
            $employeeIds = $project->members()->pluck('employees.id')->all();
            $members = $notifications->accountsForEmployees($employeeIds);
            $title = "Dự án {$project->name} đã quá hạn";
            $body = 'Hạn: '.($project->due_date?->format('d/m/Y') ?? '—');

            $notifications->notify($members, NotificationType::ProjectOverdue, $title, $body, [
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);

            $notifications->notifyAdmins(NotificationType::ProjectOverdue, $title, $body, [
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }

        $this->info(sprintf(
            'Task overdue: %d | Task due soon: %d | Sprint behind: %d | Project overdue: %d',
            $overdue->count(),
            $dueSoon->count(),
            $sprintsBehind->count(),
            $projectsOverdue->count(),
        ));

        return self::SUCCESS;
    }
}
