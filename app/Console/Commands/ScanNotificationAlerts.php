<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\NotificationService;
use App\Support\Enums\NotificationType;
use App\Support\Enums\TaskStatus;
use Illuminate\Console\Command;

/**
 * Scans for overdue / due-soon tasks and emits inbox notifications.
 * Schedule: hourly in production (app/Console/Kernel.php).
 */
class ScanNotificationAlerts extends Command
{
    protected $signature = 'notifications:scan-alerts';

    protected $description = 'Phát hiện task quá hạn / sắp đến hạn và gửi thông báo';

    public function handle(NotificationService $notifications): int
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(2)->endOfDay();

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

        $this->info("Overdue: {$overdue->count()}, Due soon: {$dueSoon->count()}");

        return self::SUCCESS;
    }
}
