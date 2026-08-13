<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\RoutineTask\Models\RoutineTask
 */
class RoutineTaskResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->enum($this->status),
            'position' => $this->position,
            'work_date' => $this->work_date?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'start_time' => $this->started_at?->format('H:i'),
            'end_time' => $this->ended_at?->format('H:i'),
            'estimate_hours' => $this->estimate_hours !== null ? (float) $this->estimate_hours : null,
            'actual_hours' => $this->actual_hours !== null ? (float) $this->actual_hours : null,
            'progress_percent' => (int) $this->progress_percent,
            'blockers' => $this->blockers,
            'risks' => $this->risks,
            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => RoutineTaskAttachmentResource::collection($this->attachments)->resolve(),
            ),
            'attachments_count' => $this->whenCounted('attachments'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
