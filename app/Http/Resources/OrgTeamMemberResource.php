<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OrgTeamMember
 */
class OrgTeamMemberResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->whenLoaded('employee', fn () => $this->person($this->employee)),
            'section_id' => $this->section_id,
            'section' => $this->whenLoaded('section', fn () => $this->section ? [
                'id' => $this->section->id,
                'title' => $this->section->title,
            ] : null),
            'branch' => $this->enum($this->branch),
            'sort_order' => $this->sort_order,
        ];
    }
}
