<?php

namespace App\Application\Task;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\NotificationDispatcher;
use App\Support\TaskActivityLogger;
use App\Support\TaskTimeliness;

class UpdateTaskStatusUseCase
{
    public function execute(Task $task, TaskStatus $newStatus, SystemAccount $actor): Task
    {
        $old = $task->status;

        $task->update(['status' => $newStatus]);

        $fresh = $task->fresh();
        TaskTimeliness::syncWorkStartedAt($fresh);
        TaskActivityLogger::statusChanged($fresh, $old, $newStatus, $actor);
        NotificationDispatcher::taskStatusChanged($fresh->load('project'), $actor);

        return $fresh;
    }
}
