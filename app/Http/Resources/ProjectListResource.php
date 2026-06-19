<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Project
 */
class ProjectListResource extends JsonResource
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
            'color' => $this->color,
            'status' => $this->enum($this->status),
            'type' => $this->enum($this->type),
            'category' => $this->enum($this->category),
            'scope' => $this->enum($this->scope),
            'scope_regions' => $this->scope_regions ?? [],
            'progress' => $this->progress(),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'budget' => $this->budget !== null ? (float) $this->budget : null,
            'actual_budget' => $this->actual_budget !== null ? (float) $this->actual_budget : null,
            'labor_cost' => $this->laborCost(),
            'member_count' => $this->whenCounted('members'),
            'task_count' => $this->whenCounted('tasks'),
            // Controller aliases the count as `open_blocker_count` (open blockers only).
            'open_blocker_count' => $this->open_blocker_count ?? 0,
            'created_at' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toDateString(),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'color' => $this->department->color,
            ] : null),
            // Đội (lĩnh vực) của người quản lý — dùng để nhóm Kanban "Đội công nghệ".
            'org_team' => $this->org_team ?? null,
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
