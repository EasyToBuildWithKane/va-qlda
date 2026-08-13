<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use App\Support\Enums\TaskStatus;

class UpdateRoutineTaskUseCase
{
    use MapsRoutineTaskFields;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(RoutineTask $task, array $data): RoutineTask
    {
        $currentStatus = $task->status instanceof TaskStatus ? $task->status : null;

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = TaskStatus::tryFrom((string) $data['status']);
            if ($status === null || ! in_array($status->value, RoutineTask::allowedStatusValues(), true)) {
                unset($data['status']);
            }
        }

        $payload = $this->mapWritableFields($data, $currentStatus, (int) $task->progress_percent);

        if (isset($payload['status']) && $payload['status'] === TaskStatus::Done) {
            $payload['completed_at'] = $task->completed_at ?? now();
        }

        if ($payload !== []) {
            $task->fill($payload)->save();
        }

        return $task->refresh();
    }
}
