<?php

namespace App\Support\Project;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Department\DepartmentScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ai được thấy dự án: admin-tier, chủ/thành viên dự án, hoặc thuộc phòng phụ trách / phòng liên đới
 * (phần "phòng liên đới" chỉ áp dụng cho tài khoản giữ quyền `department.view_scope` — xem
 * {@see DepartmentScope::hasDepartmentWideScope()}).
 */
final class ProjectVisibility
{
    /**
     * @return list<int>
     */
    public static function departmentIdsForAccount(SystemAccount $account): array
    {
        return DepartmentScope::idsForAccount($account);
    }

    /**
     * @return list<int>
     */
    public static function departmentIdsForEmployee(?Employee $employee): array
    {
        return DepartmentScope::idsForEmployee($employee);
    }

    /**
     * @return list<int>
     */
    public static function audienceDepartmentIds(Project $project): array
    {
        $ids = [];
        if ($project->department_id) {
            $ids[] = (int) $project->department_id;
        }
        foreach ($project->scope_departments ?? [] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function canView(SystemAccount $account, Project $project): bool
    {
        if ($account->isAdminTier()) {
            return true;
        }

        if (self::isManagerOrMember($account, $project)) {
            return true;
        }

        return self::sharesDepartment($account, $project);
    }

    public static function sharesDepartment(SystemAccount $account, Project $project): bool
    {
        if (! DepartmentScope::hasDepartmentWideScope($account)) {
            return false;
        }

        $mine = self::departmentIdsForAccount($account);
        if ($mine === []) {
            return false;
        }

        return count(array_intersect($mine, self::audienceDepartmentIds($project))) > 0;
    }

    public static function constrainIndex(Builder $query, SystemAccount $account): void
    {
        if ($account->isAdminTier()) {
            return;
        }

        $employeeId = $account->employee_id;
        $deptIds = DepartmentScope::hasDepartmentWideScope($account)
            ? self::departmentIdsForAccount($account)
            : [];

        $query->where(function (Builder $q) use ($employeeId, $deptIds) {
            $matched = false;

            if ($employeeId) {
                $matched = true;
                $q->where('manager_id', $employeeId)
                    ->orWhereHas('members', function ($m) use ($employeeId) {
                        $m->where('employee_id', $employeeId);
                        Employee::applyActiveProjectPivotFilter($m);
                    });
            }

            if ($deptIds !== []) {
                if ($matched) {
                    self::orWhereAudienceDepartments($q, $deptIds);
                } else {
                    self::whereAudienceDepartments($q, $deptIds);
                }

                return;
            }

            if (! $matched) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    /**
     * @param  list<int>  $deptIds
     */
    public static function whereAudienceDepartments(Builder $query, array $deptIds): void
    {
        $query->where(function (Builder $q) use ($deptIds) {
            $q->whereIn('department_id', $deptIds);
            foreach ($deptIds as $id) {
                self::orWhereRelatedDepartment($q, $id);
            }
        });
    }

    /**
     * @param  list<int>  $deptIds
     */
    public static function orWhereAudienceDepartments(Builder $query, array $deptIds): void
    {
        $query->orWhere(function (Builder $q) use ($deptIds) {
            $q->whereIn('department_id', $deptIds);
            foreach ($deptIds as $id) {
                self::orWhereRelatedDepartment($q, $id);
            }
        });
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string, code: string, color: string}>
     */
    public static function presentDepartments(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $order = array_flip($ids);

        return Department::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'code', 'color'])
            ->sortBy(fn (Department $d) => $order[$d->id] ?? 999)
            ->values()
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'color' => $d->color,
            ])
            ->all();
    }

    private static function isManagerOrMember(SystemAccount $account, Project $project): bool
    {
        if ($account->employee_id === null) {
            return false;
        }

        if ($account->employee_id === $project->manager_id) {
            return true;
        }

        return $project->members()
            ->where('employee_id', $account->employee_id)
            ->where(function ($q) {
                Employee::applyActiveProjectPivotFilter($q);
            })
            ->exists();
    }

    private static function orWhereRelatedDepartment(Builder $query, int $id): void
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orWhereJsonContains('scope_departments', $id);

            return;
        }

        $query->orWhereRaw(
            "',' || replace(replace(replace(COALESCE(scope_departments, ''), ' ', ''), '[', ''), ']', '') || ',' LIKE ?",
            ['%,'.$id.',%'],
        );
    }
}
