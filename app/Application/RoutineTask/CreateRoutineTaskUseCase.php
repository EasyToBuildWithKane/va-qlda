<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use App\Support\Enums\TaskStatus;

class CreateRoutineTaskUseCase
{
    /**
     * @param  array{title: string, description?: string|null, status?: string|null}  $data
     */
    public function execute(int $employeeId, array $data): RoutineTask
    {
        $status = TaskStatus::tryFrom((string) ($data['status'] ?? TaskStatus::Todo->value))
            ?? TaskStatus::Todo;

        if (! in_array($status->value, RoutineTask::allowedStatusValues(), true)) {
            $status = TaskStatus::Todo;
        }

        $nextPosition = (int) RoutineTask::query()
            ->forEmployee($employeeId)
            ->max('position') + 1;

        return RoutineTask::query()->create([
            'employee_id' => $employeeId,
            'title' => trim((string) $data['title']),
            'description' => isset($data['description']) ? trim((string) $data['description']) ?: null : null,
            'status' => $status,
            'position' => $nextPosition,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ]);
    }
}
