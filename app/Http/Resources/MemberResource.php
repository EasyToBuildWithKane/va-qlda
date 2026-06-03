<?php

namespace App\Http\Resources;

use App\Support\Enums\RateType;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project member — an Employee carrying the project_member pivot.
 *
 * @mixin \App\Models\Employee
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Employee $employee */
        $employee = $this->resource;
        $pivot = $employee->getRelationValue('pivot');
        $rateType = $pivot?->rate_type ? RateType::from($pivot->rate_type) : null;

        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($this->avatar_path),
            'role_title' => $this->role_title,
            'project_role' => $pivot?->role,
            'rate_type' => $rateType?->value,
            'rate_type_label' => $rateType?->label(),
            'rate' => $pivot?->rate !== null ? (float) $pivot->rate : null,
            'allocation' => $pivot?->allocation,
            'joined_at' => $pivot?->joined_at,
            'is_active' => (bool) ($pivot?->is_active ?? true),
        ];
    }
}
