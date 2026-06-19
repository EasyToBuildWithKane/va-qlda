<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'status' => $this->enum($this->status),
            'type' => $this->enum($this->type),
            'category' => $this->enum($this->category),
            'scope' => $this->enum($this->scope),
            'scope_regions' => $this->scope_regions ?? [],
            'scope_departments' => $this->scope_departments ?? [],
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'budget' => $this->budget !== null ? (float) $this->budget : null,
            'actual_budget' => $this->actual_budget !== null ? (float) $this->actual_budget : null,
            'labor_cost' => $this->laborCost(),
            'progress' => $this->progress(),
            'is_active' => $this->is_active,
            'manager_id' => $this->manager_id,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'color' => $this->department->color,
            ] : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            'sprints' => SprintResource::collection($this->whenLoaded('sprints')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'blockers' => BlockerResource::collection($this->whenLoaded('blockers')),
            'attachments' => ProjectAttachmentResource::collection($this->whenLoaded('attachments')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'manage' => $user->can('manage', $this->resource),
                'contribute' => $user->can('contribute', $this->resource),
            ] : null,
        ];
    }
}
