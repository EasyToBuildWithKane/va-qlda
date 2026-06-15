<?php

namespace App\Support\Profile;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use Illuminate\Support\Collection;

/**
 * Org-chart semantics for employee profile (direct manager, kiêm nhiệm, mã phòng ban QLDA).
 */
class ProfileOrgRelations
{
    /**
     * Quản lý trực tiếp = người quản lý ở cấp trên trong sơ đồ (leader của nhóm cha),
     * hoặc leader nhóm hiện tại khi không có cấp cha.
     */
    public static function directManager(Employee $employee): ?Employee
    {
        if (! $employee->relationLoaded('orgMemberships')) {
            return null;
        }

        foreach ($employee->orgMemberships as $membership) {
            $team = $membership->team;
            if ($team === null) {
                continue;
            }

            $parent = $team->relationLoaded('parent') ? $team->parent : null;
            if ($parent?->leader && $parent->leader_id !== $employee->id) {
                return $parent->leader;
            }

            if ($team->leader_id !== $employee->id && $team->leader) {
                return $team->leader;
            }
        }

        foreach (self::ledTeams($employee) as $team) {
            $parent = $team->relationLoaded('parent') ? $team->parent : null;
            if ($parent?->leader && $parent->leader_id !== $employee->id) {
                return $parent->leader;
            }
        }

        return null;
    }

    /**
     * Chức danh kiêm nhiệm: vai trò trưởng nhóm / quản lý cấp trên trên sơ đồ QLDA.
     */
    public static function concurrentPositionLabel(Employee $employee): ?string
    {
        $labels = self::ledTeams($employee)
            ->map(fn (OrgTeam $team) => self::leaderRoleLabel($team).' · '.$team->name)
            ->values()
            ->all();

        if ($labels === []) {
            return null;
        }

        return implode('; ', $labels);
    }

    /**
     * Mã phòng ban từ bảng departments (QLDA), không dùng department_id CMS.
     */
    public static function departmentCode(array $meta): ?string
    {
        $name = trim((string) ($meta['department_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $byName = Department::query()->where('name', $name)->value('code');
        if (is_string($byName) && $byName !== '') {
            return $byName;
        }

        $byCode = Department::query()->where('code', $name)->value('code');

        return is_string($byCode) && $byCode !== '' ? $byCode : null;
    }

    /**
     * @return Collection<int, OrgTeam>
     */
    private static function ledTeams(Employee $employee): Collection
    {
        if ($employee->relationLoaded('ledTeams')) {
            return $employee->ledTeams;
        }

        return OrgTeam::query()
            ->where('leader_id', $employee->id)
            ->with([
                'parent:id,name,leader_id,parent_id,level',
                'parent.leader:id,full_name,avatar_path,code,email,role_title',
            ])
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    private static function leaderRoleLabel(OrgTeam $team): string
    {
        return match ((int) $team->level) {
            1 => 'Quản lý cấp trên',
            2 => 'Trưởng nhóm',
            default => 'Trưởng đơn vị',
        };
    }
}
