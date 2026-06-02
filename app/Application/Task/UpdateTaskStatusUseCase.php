<?php

namespace App\Application\Task;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\TaskStatus;

/**
 * @deprecated Prefer PatchTaskUseCase for workspace status changes.
 */
class UpdateTaskStatusUseCase
{
    public function __construct(
        private readonly PatchTaskUseCase $patchTask,
    ) {}

    public function execute(Task $task, TaskStatus $newStatus, SystemAccount $actor): Task
    {
        return $this->patchTask->execute(
            $task,
            ['status' => $newStatus->value],
            $actor,
        )['task'];
    }
}
