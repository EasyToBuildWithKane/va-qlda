<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;

class DeleteRoutineTaskUseCase
{
    public function execute(RoutineTask $task): void
    {
        $task->delete();
    }
}
