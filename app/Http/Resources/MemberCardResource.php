<?php

namespace App\Http\Resources;

use App\Support\Profile\Seniority;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact employee card for the member directory grid.
 *
 * @mixin \App\Models\Employee
 */
class MemberCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Employee $e */
        $e = $this->resource;
        $skills = is_array($e->skills) ? array_values(array_filter($e->skills, 'is_string')) : [];

        return [
            'id' => $e->id,
            'code' => $e->code,
            'name' => $e->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
            'role_title' => $e->role_title,
            'email' => $e->email,
            'is_active' => (bool) $e->is_active,
            'seniority' => Seniority::for($e),
            'skills_preview' => array_slice($skills, 0, 5),
            'skills_total' => count($skills),
            'projects_count' => (int) ($e->projects_count ?? 0),
            'projects_preview' => $e->relationLoaded('projects')
                ? $e->projects->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'color' => $p->color,
                ])->values()->all()
                : [],
        ];
    }
}
