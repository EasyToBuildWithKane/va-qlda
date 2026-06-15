<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Department
 */
class DepartmentResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'manager' => $this->whenLoaded('manager', fn () => $this->person($this->manager)),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($e) => $this->person($e))->values()),
            'project_count' => $this->whenCounted('projects'),
            'member_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }
}
