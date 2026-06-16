<?php

namespace App\Support\Performance;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use Illuminate\Support\Collection;

/**
 * Nhãn đơn vị (root org team) cho nhân sự — gồm thành viên roster và trưởng nhóm trên sơ đồ.
 */
class EmployeeOrgUnitResolver
{
    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array<int, string|null>
     */
    public static function labelsFor(Collection $employeeIds): array
    {
        if ($employeeIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, OrgTeam> $byId */
        $byId = OrgTeam::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'leader_id', 'level', 'sort_order'])
            ->keyBy('id');

        $resolveRoot = function (OrgTeam $team) use ($byId): OrgTeam {
            $current = $team;
            while ($current->parent_id !== null && $byId->has($current->parent_id)) {
                $current = $byId->get($current->parent_id);
            }

            return $current;
        };

        /** @var array<int, string|null> $labels */
        $labels = [];

        OrgTeamMember::query()
            ->whereIn('employee_id', $employeeIds)
            ->with('team:id,name,parent_id')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('employee_id')
            ->each(function (Collection $members, $employeeId) use (&$labels, $resolveRoot): void {
                $team = $members->first()?->team;
                if ($team === null) {
                    return;
                }
                $labels[(int) $employeeId] = $resolveRoot($team)->name;
            });

        OrgTeam::query()
            ->where('is_active', true)
            ->whereIn('leader_id', $employeeIds)
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'leader_id'])
            ->groupBy('leader_id')
            ->each(function (Collection $ledTeams, $leaderId) use (&$labels, $resolveRoot): void {
                $id = (int) $leaderId;
                if (isset($labels[$id])) {
                    return;
                }
                $team = $ledTeams->first();
                if ($team instanceof OrgTeam) {
                    $labels[$id] = $resolveRoot($team)->name;
                }
            });

        $missingIds = $employeeIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => empty($labels[$id] ?? null))
            ->values();

        if ($missingIds->isNotEmpty()) {
            Employee::query()
                ->whereIn('id', $missingIds)
                ->get(['id', 'meta'])
                ->each(function (Employee $employee) use (&$labels): void {
                    $meta = $employee->meta;
                    if (! is_array($meta)) {
                        return;
                    }
                    $unit = isset($meta['unit_name']) ? trim((string) $meta['unit_name']) : '';
                    if ($unit !== '') {
                        $labels[$employee->id] = $unit;
                    }
                });
        }

        return $labels;
    }
}
