<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsEntities;
use App\Support\Enums\SystemRole;
use App\Support\Profile\ProfileOrgRelations;
use App\Support\Profile\ProfileStats;
use App\Support\Profile\Seniority;
use App\Support\Profile\SkillCatalog;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The identity + capability core of a person's profile page (My Profile and
 * Member Profile share it). Skill matrix is built from employees.skills and
 * meta.skill_details via SkillCatalog.
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
                ? $this->systemAccountRole($e->account)
                : null,
            'socials' => [
                'github' => $socials['github'] ?? null,
                'linkedin' => $socials['linkedin'] ?? null,
                'portfolio' => $socials['portfolio'] ?? null,
                'website' => $socials['website'] ?? null,
            ],
            'skills' => SkillCatalog::build($e->skills, $meta['skill_details'] ?? null),
            'stats' => ProfileStats::for($e),
            'teams' => $this->teams(),
            'manager' => $this->manager(),
            'current_projects' => $this->currentProjects(),
            'hr_info' => $this->hrInfo($e, $meta),
            'can' => $user ? [
                'update' => $user->can('update', $e),
            ] : null,
        ];
    }

    /**
     * HR fields mirrored from CMS `user_info` (synced into employees + meta).
     *
     * @return array<string, mixed>
     */
    private function hrInfo(\App\Models\Employee $e, array $meta): array
    {
        return [
            'code' => $e->code,
            'phone' => $e->phone,
            'company_name' => $meta['company_name'] ?? null,
            'department_name' => $meta['department_name'] ?? null,
            'unit_name' => $meta['unit_name'] ?? null,
            'headquarter_name' => $meta['headquarter_name'] ?? null,
            'position_name' => $meta['position_name'] ?? null,
            'concurrent_position_name' => ProfileOrgRelations::concurrentPositionLabel($e)
                ?? $meta['concurrent_position_name'] ?? null,
            'start_working_date' => $e->join_date?->toDateString(),
            'department_code' => ProfileOrgRelations::departmentCode($meta),
            'company_id' => $meta['company_id'] ?? null,
        ];
    }

    /**
     * Org-team memberships (with whether this person leads the team).
     *
     * Includes teams the person leads even when they have no explicit member
     * row, so a team leader still sees their team on the profile.
     *
     * @return list<array<string, mixed>>
     */
    private function teams(): array
    {
        $teams = collect();

        if ($this->resource->relationLoaded('orgMemberships')) {
            $teams = $this->resource->orgMemberships
                ->map(fn ($m) => $m->team ? [
                    'id' => $m->team->id,
                    'name' => $m->team->name,
                    'section' => $m->section?->title,
                    'is_leader' => $m->team->leader_id === $this->resource->id,
                ] : null)
                ->filter()
                ->values();
        }

        if ($this->resource->relationLoaded('ledTeams')) {
            $existing = $teams->pluck('id')->flip();
            $teams = $teams->merge(
                $this->resource->ledTeams
                    ->reject(fn ($team) => $existing->has($team->id))
                    ->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'section' => null,
                        'is_leader' => true,
                    ])
            );
        }

        return $teams->values()->all();
    }

    /**
     * Quản lý trực tiếp theo sơ đồ tổ chức QLDA (cấp trên).
     *
     * @return array{id:int, name:string, avatar_path:string|null, code?:string, email?:string|null, role_title?:string|null}|null
     */
    private function manager(): ?array
    {
        $leader = ProfileOrgRelations::directManager($this->resource);

        return $leader ? $this->person($leader) : null;
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
            ->merge(
                $this->resource->relationLoaded('managedProjects')
                    ? $this->resource->managedProjects
                    : collect(),
            )
            ->unique('id')
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'color' => $p->color,
                'role' => $p->pivot->role ?? ($this->resource->id === $p->manager_id ? 'manager' : null),
                'allocation' => $p->pivot->allocation ?? null,
                'joined_at' => isset($p->pivot->joined_at) ? (string) $p->pivot->joined_at : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Avoid 500 when legacy rows store an invalid system_accounts.role value.
     *
     * @return array{value:string, label:string, color:string|null}|null
     */
    private function systemAccountRole(\App\Models\SystemAccount $account): ?array
    {
        $raw = $account->getRawOriginal('role');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $role = SystemRole::tryFrom($raw);

        return $role ? $this->enum($role) : null;
    }
}
