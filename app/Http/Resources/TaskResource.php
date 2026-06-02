<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'sprint_id' => $this->sprint_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->enum($this->status),
            'priority' => $this->enum($this->priority),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->person($this->assignee)),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->person($this->reporter)),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'estimate_hours' => $this->estimate_hours !== null ? (float) $this->estimate_hours : null,
            'progress' => (int) $this->progress,
            'order_column' => $this->order_column,
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->pluck('id')->values()),
            'logged_hours' => $this->whenLoaded('worklogs', fn () => (float) $this->worklogs->sum('hours')),
            'labor_cost' => $this->whenLoaded('worklogs', fn () => (float) $this->worklogs->sum('cost')),
        ];
    }
}
