<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Profile\Seniority;
use App\Support\Profile\SkillCatalog;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The identity + capability core of a person's profile page (My Profile and
 * Member Profile share it). Heavier, query-driven sections — project history,
 * performance stats, activity feed — are passed as sibling props by the
 * controller and gated by permission there.
 *
 * @mixin \App\Models\Employee
 */
class EmployeeProfileResource extends JsonResource
{
    use PresentsEntities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Employee $e */
        $e = $this->resource;
        $meta = is_array($e->meta) ? $e->meta : [];
        $user = $request->user();
        $socials = is_array($meta['socials'] ?? null) ? $meta['socials'] : [];

        return [
            'id' => $e->id,
            'code' => $e->code,
            'name' => $e->full_name,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($e->avatar_path),
            'role_title' => $e->role_title,
            'email' => $e->email,
            'phone' => $e->phone,
            'join_date' => $e->join_date?->toDateString(),
            'is_active' => (bool) $e->is_active,
            'bio' => $meta['bio'] ?? null,
            'location' => $meta['location'] ?? null,
            'seniority' => Seniority::for($e),
            'account_role' => ($e->relationLoaded('account') && $e->account)
                ? $this->enum($e->account->role)
                : null,
            'socials' => [
                'github' => $socials['github'] ?? null,
                'linkedin' => $socials['linkedin'] ?? null,
                'portfolio' => $socials['portfolio'] ?? null,
                'website' => $socials['website'] ?? null,
            ],
            'skills' => SkillCatalog::build($e->skills, $meta['skill_details'] ?? null),
            'teams' => $this->teams(),
            'manager' => $this->manager(),
            'current_projects' => $this->currentProjects(),
            'can' => $user ? [
                'update' => $user->can('update', $e),
                'view_performance' => $user->can('viewPerformance', $e),
            ] : null,
        ];
    }

    /**
     * Org-team memberships (with whether this person leads the team).
     *
     * @return list<array<string, mixed>>
     */
    private function teams(): array
    {
        if (! $this->resource->relationLoaded('orgMemberships')) {
            return [];
        }

        return $this->resource->orgMemberships
            ->map(fn ($m) => $m->team ? [
                'id' => $m->team->id,
                'name' => $m->team->name,
                'section' => $m->section?->name,
                'is_leader' => $m->team->leader_id === $this->resource->id,
            ] : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Manager = the leader of the first team this person belongs to but does
     * not lead. Null when they have no team or lead all of them.
     *
     * @return array{id:int, name:string, avatar_path:string|null, code?:string, email?:string|null, role_title?:string|null}|null
     */
    private function manager(): ?array
    {
        if (! $this->resource->relationLoaded('orgMemberships')) {
            return null;
        }

        foreach ($this->resource->orgMemberships as $m) {
            $leader = $m->team?->leader;
            if ($leader && $leader->id !== $this->resource->id) {
                return $this->person($leader);
            }
        }

        return null;
    }

    /**
     * Active project memberships, lightweight, for the hero strip.
     *
     * @return list<array<string, mixed>>
     */
    private function currentProjects(): array
    {
        if (! $this->resource->relationLoaded('projects')) {
            return [];
        }

        return $this->resource->projects
            ->filter(fn ($p) => (bool) ($p->pivot->is_active ?? true))
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'color' => $p->color,
                'role' => $p->pivot->role,
                'allocation' => $p->pivot->allocation,
            ])
            ->values()
            ->all();
    }
}
