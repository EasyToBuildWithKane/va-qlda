<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;

class DeleteRoutineTaskUseCase
{
    public function __construct(
        private readonly StoreRoutineTaskAttachmentsUseCase $attachments,
    ) {}

    public function execute(RoutineTask $task): void
    {
        $this->attachments->deleteDiskFiles($task);
        $task->delete();
    }
}
