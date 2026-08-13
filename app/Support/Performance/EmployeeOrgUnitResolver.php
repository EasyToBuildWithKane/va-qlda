<?php

namespace App\Support\Performance;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use Illuminate\Support\Collection;

/**
 * Nhãn đơn vị cho nhân sự — ưu tiên phòng/tổ HRM (employees.meta), fallback sơ đồ OrgTeam.
 */
class EmployeeOrgUnitResolver
{
    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array<int, array{label: string|null, department: string|null, unit: string|null}>
     */
    public static function detailsFor(Collection $employeeIds): array
    {
        if ($employeeIds->isEmpty()) {
            return [];
        }

        /** @var array<int, array{label: string|null, department: string|null, unit: string|null}> $details */
        $details = [];

        Employee::query()
            ->whereIn('id', $employeeIds)
            ->get(['id', 'meta'])
            ->each(function (Employee $employee) use (&$details): void {
                $meta = is_array($employee->meta) ? $employee->meta : [];
                $department = self::trimMeta($meta['department_name'] ?? null);
                $unit = self::trimMeta($meta['unit_name'] ?? null);
                $label = self::composeLabel($department, $unit);
                if ($label !== null || $department !== null || $unit !== null) {
                    $details[$employee->id] = [
                        'label' => $label,
                        'department' => $department,
                        'unit' => $unit,
                    ];
                }
            });

        $missingIds = $employeeIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => empty($details[$id]['label'] ?? null))
            ->values();

        if ($missingIds->isEmpty()) {
            return $details;
        }

        $orgLabels = self::orgTeamLabels($missingIds);
        foreach ($orgLabels as $id => $name) {
            if (isset($details[$id]) && filled($details[$id]['label'])) {
                continue;
            }
            $details[$id] = [
                'label' => $name,
                'department' => $details[$id]['department'] ?? $name,
                'unit' => $details[$id]['unit'] ?? null,
            ];
        }

        return $details;
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array<int, string|null>
     */
    public static function labelsFor(Collection $employeeIds): array
    {
        $out = [];
        foreach (self::detailsFor($employeeIds) as $id => $row) {
            $out[$id] = $row['label'];
        }

        return $out;
    }

    private static function composeLabel(?string $department, ?string $unit): ?string
    {
        if ($department !== null && $unit !== null && strcasecmp($department, $unit) !== 0) {
            return $department.' · '.$unit;
        }

        return $department ?? $unit;
    }

    private static function trimMeta(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s !== '' ? $s : null;
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array<int, string>
     */
    private static function orgTeamLabels(Collection $employeeIds): array
    {
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

        /** @var array<int, string> $labels */
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

        return $labels;
    }
}
