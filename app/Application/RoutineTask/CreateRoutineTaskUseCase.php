<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use App\Support\Enums\TaskStatus;

class CreateRoutineTaskUseCase
{
    use MapsRoutineTaskFields;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $employeeId, array $data): RoutineTask
    {
        $status = TaskStatus::tryFrom((string) ($data['status'] ?? TaskStatus::Todo->value))
            ?? TaskStatus::Todo;

        if (! in_array($status->value, RoutineTask::allowedStatusValues(), true)) {
            $status = TaskStatus::Todo;
        }

        $data['status'] = $status->value;

        $nextPosition = (int) RoutineTask::query()
            ->forEmployee($employeeId)
            ->max('position') + 1;

        $fields = $this->mapWritableFields($data, $status);
        $fields['employee_id'] = $employeeId;
        $fields['position'] = $nextPosition;
        $fields['status'] = $status;
        $fields['completed_at'] = $status === TaskStatus::Done ? now() : null;

        if (! array_key_exists('work_date', $data) && empty($fields['work_date'])) {
            $fields['work_date'] = now()->toDateString();
        }

        return RoutineTask::query()->create($fields);
    }
}
