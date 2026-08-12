<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Enums\TestCaseRunResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TestCase
 */
class TestCaseResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'code' => $this->code ?? ('TC-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT)),
            'project_id' => $this->project_id,
            'suite_id' => $this->suite_id,
            'suite' => $this->whenLoaded('suite', fn () => $this->suite ? [
                'id' => $this->suite->id,
                'name' => $this->suite->name,
            ] : null),
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ] : null),
            'blocker_id' => $this->blocker_id,
            'title' => $this->title,
            'preconditions' => $this->preconditions,
            'steps' => $this->steps ?? [],
            'expected_result' => $this->expected_result,
            'priority' => $this->enum($this->priority),
            'status' => $this->enum($this->status),
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'last_result' => $this->last_result
                ? $this->enum(TestCaseRunResult::tryFrom($this->last_result))
                : null,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_run_by' => $this->whenLoaded('lastRunBy', fn () => $this->person($this->lastRunBy)),
            'last_actual_result' => $this->last_actual_result,
            'last_run_note' => $this->last_run_note,
            'not_run' => $this->isNotRun(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'execute' => $user->can('execute', $this->resource),
            ] : null,
        ];
    }
}
