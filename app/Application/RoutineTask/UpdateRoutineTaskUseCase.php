<?php

namespace App\Application\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use App\Support\Enums\TaskStatus;

class UpdateRoutineTaskUseCase
{
    /**
     * @param  array{title?: string, description?: string|null, status?: string|null, position?: int}  $data
     */
    public function execute(RoutineTask $task, array $data): RoutineTask
    {
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = trim((string) $data['title']);
        }

        if (array_key_exists('description', $data)) {
            $description = $data['description'];
            $payload['description'] = $description === null || $description === ''
                ? null
                : trim((string) $description);
        }

        if (array_key_exists('position', $data)) {
            $payload['position'] = max(0, (int) $data['position']);
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = TaskStatus::tryFrom((string) $data['status']);
            if ($status !== null && in_array($status->value, RoutineTask::allowedStatusValues(), true)) {
                $payload['status'] = $status;
                $payload['completed_at'] = $status === TaskStatus::Done
                    ? ($task->completed_at ?? now())
                    : null;
            }
        }

        if ($payload !== []) {
            $task->fill($payload)->save();
        }

        return $task->refresh();
    }
}
