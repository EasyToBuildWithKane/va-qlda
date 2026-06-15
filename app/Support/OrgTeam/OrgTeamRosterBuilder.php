<?php

namespace App\Support\OrgTeam;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Support\Enums\OrgTeamMemberBranch;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrgTeamRosterBuilder
{
    /**
     * @param  bool  $membersOnly  Chỉ thành viên gán trong nhóm (bỏ quản lý trên thẻ sơ đồ).
     * @return Collection<int, array<string, mixed>>
     */
    public static function allRows(bool $membersOnly = false): Collection
    {
        $teams = OrgTeam::query()
            ->with('leader:id,full_name,code,email,avatar_path,role_title,is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /** @var Collection<int, OrgTeam> $byId */
        $byId = $teams->keyBy('id');

        $resolveRoot = function (OrgTeam $team) use ($byId): OrgTeam {
            $current = $team;
            while ($current->parent_id !== null && $byId->has($current->parent_id)) {
                $current = $byId->get($current->parent_id);
            }

            return $current;
        };

        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = [];

        $ensureEmployee = function (Employee $employee) use (&$byEmployee): int {
            $id = $employee->id;
            if (! isset($byEmployee[$id])) {
                $byEmployee[$id] = [
                    'id' => $id,
                    'code' => $employee->code,
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'role_title' => $employee->role_title,
                    'avatar_path' => PublicMediaUrl::fromPublicDisk($employee->avatar_path),
                    'is_active' => (bool) $employee->is_active,
                    'assignments' => [],
                ];
            }

            return $id;
        };

        $pathLabel = function (OrgTeam $team) use ($byId): string {
            $parts = [];
            $current = $team;
            while ($current !== null) {
                array_unshift($parts, $current->name);
                $current = $current->parent_id !== null ? $byId->get($current->parent_id) : null;
            }

            return implode(' › ', $parts);
        };

        if (! $membersOnly) {
            foreach ($teams as $team) {
                if ($team->leader_id && $team->leader) {
                    $employeeId = $ensureEmployee($team->leader);
                    $root = $resolveRoot($team);
                    $byEmployee[$employeeId]['assignments'][] = [
                        'team_id' => $team->id,
                        'root_team_id' => $root->id,
                        'path' => $pathLabel($team),
                        'team_name' => $team->name,
                        'root_name' => $root->name,
                        'section' => null,
                        'branch' => null,
                        'branch_label' => null,
                        'is_leader' => true,
                    ];
                }
            }
        }

        $memberships = OrgTeamMember::query()
            ->with([
                'employee:id,full_name,code,email,avatar_path,role_title,is_active',
                'section:id,title',
                'team:id,name,parent_id,level,leader_id',
            ])
            ->orderBy('sort_order')
            ->get();

        foreach ($memberships as $membership) {
            $employee = $membership->employee;
            $team = $membership->team;
            if ($employee === null || $team === null) {
                continue;
            }
            if ($team->leader_id === $employee->id) {
                continue;
            }

            $employeeId = $ensureEmployee($employee);
            $root = $resolveRoot($team);
            $branch = $membership->branch;
            $byEmployee[$employeeId]['assignments'][] = [
                'team_id' => $team->id,
                'root_team_id' => $root->id,
                'path' => $pathLabel($team),
                'team_name' => $team->name,
                'root_name' => $root->name,
                'section' => $membership->section?->title,
                'branch' => $branch?->value,
                'branch_label' => $branch?->label(),
                'is_leader' => false,
            ];
        }

        return collect(array_values($byEmployee))
            ->sortBy(fn (array $row) => mb_strtolower((string) $row['name']))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $memberRows  Thành viên trong bảng (không gồm quản lý).
     * @param  Collection<int, array<string, mixed>>|null  $chartRows  Toàn bộ người trên sơ đồ (để đếm quản lý).
     * @return array{total: int, leaders: int, active: int, inactive: int}
     */
    public static function summary(Collection $memberRows, ?Collection $chartRows = null): array
    {
        $chart = $chartRows ?? $memberRows;
        $leaders = $chart->filter(function (array $row) {
            foreach ($row['assignments'] as $a) {
                if ($a['is_leader']) {
                    return true;
                }
            }

            return false;
        })->count();

        $active = $memberRows->where('is_active', true)->count();
        $total = $memberRows->count();

        return [
            'total' => $total,
            'leaders' => $leaders,
            'active' => $active,
            'inactive' => max(0, $total - $active),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function filtered(Request $request, bool $membersOnly = false): Collection
    {
        $rows = self::allRows($membersOnly);
        $search = trim((string) $request->query('q'));
        $rootId = $request->integer('root_id') ?: null;
        $teamId = $request->integer('team_id') ?: null;
        $branch = $request->query('branch');
        $status = $request->query('status');

        $descendantIds = null;
        if ($teamId) {
            $team = OrgTeam::query()->find($teamId);
            $descendantIds = $team
                ? array_merge([$team->id], $team->descendantIds())
                : [$teamId];
        }

        return $rows->filter(function (array $row) use ($search, $rootId, $teamId, $descendantIds, $branch, $status) {
            if ($status === 'active' && ! $row['is_active']) {
                return false;
            }
            if ($status === 'inactive' && $row['is_active']) {
                return false;
            }

            if ($search !== '') {
                $hay = mb_strtolower(implode(' ', array_filter([
                    $row['name'],
                    $row['code'],
                    $row['email'],
                    $row['role_title'],
                ])));
                if (! str_contains($hay, mb_strtolower($search))) {
                    return false;
                }
            }

            if ($rootId || $teamId || ($branch && $branch !== '')) {
                $matched = false;
                foreach ($row['assignments'] as $assignment) {
                    if ($rootId && (int) $assignment['root_team_id'] !== $rootId) {
                        continue;
                    }
                    if ($descendantIds !== null && ! in_array((int) $assignment['team_id'], $descendantIds, true)) {
                        continue;
                    }
                    if ($branch && $branch !== '' && $assignment['branch'] !== $branch) {
                        continue;
                    }
                    $matched = true;
                    break;
                }
                if (! $matched) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @return list<array{value: string, label: string, color: string}>
     */
    public static function branchOptions(): array
    {
        return OrgTeamMemberBranch::options();
    }
}
