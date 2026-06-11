<?php

namespace App\Application\Task\Concerns;

use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\TaskActivityLogger;
use App\Support\TaskCompletion;

trait AppliesTaskStatusRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function applyStatusTransitionRules(Task $task, array $validated, SystemAccount $actor): array
    {
        $newStatus = $validated['status'] ?? null;

        TaskCompletion::guardStatusChange($task, $newStatus, $actor);
        TaskCompletion::guardCompletePayload($task, $validated);

        if ($newStatus === TaskStatus::Done->value && $task->status !== TaskStatus::Done) {
            $actual = (float) ($validated['actual_hours'] ?? 0);
            $note = isset($validated['completion_note']) ? (string) $validated['completion_note'] : null;
            $validated = array_merge($validated, TaskCompletion::completionAttributes($task, $actual, $note));
        }

        if (
            $newStatus !== null
            && $newStatus !== TaskStatus::Done->value
            && $task->status === TaskStatus::Done
            && TaskCompletion::actorMayUnlockStatus($actor)
        ) {
            $validated = array_merge($validated, TaskCompletion::clearCompletionAttributes());
        }

        return $validated;
    }

    protected function logStatusSideEffects(
        Task $fresh,
        string $previousStatus,
        SystemAccount $actor,
        bool $statusChanged,
    ): void {
        if (! $statusChanged) {
            return;
        }

        if ($fresh->status === TaskStatus::Done && $previousStatus !== TaskStatus::Done->value) {
            TaskActivityLogger::completed($fresh, $actor);
        }

        TaskActivityLogger::statusChanged(
            $fresh,
            $previousStatus,
            $fresh->status->value,
            $actor,
        );
    }
}
