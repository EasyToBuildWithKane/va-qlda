<?php

namespace App\Observers;

use App\Events\TaskStatusChanged;
use App\Models\Task;
use App\Support\Enums\TaskStatus;

class TaskObserver
{
    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        if ($task->isDirty('status') && $task->status === TaskStatus::Done) {
            TaskStatusChanged::dispatch($task);
        }
    }
}
