<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OrgTeam
 */
class OrgTeamResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'level' => $this->level,
            'level_label' => self::levelLabel($this->level),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'leader' => $this->whenLoaded('leader', fn () => $this->person($this->leader)),
            'sections' => OrgTeamSectionResource::collection($this->whenLoaded('sections')),
            'members' => OrgTeamMemberResource::collection($this->whenLoaded('members')),
            'can' => $user ? [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ] : null,
        ];
    }

    public static function levelLabel(int $level): string
    {
        return match ($level) {
            1 => 'Cấu trúc',
            2 => 'Đơn vị',
            default => "Tầng {$level}",
        };
    }
}
