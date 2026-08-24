<?php

namespace App\Support\Department;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;

/**
 * Phòng ban của tài khoản (HRM meta → pivot department_member) và cổng
 * "xem toàn bộ phòng ban" dùng chung cho mọi module cần lọc theo phòng ban
 * (Project, Báo cáo ngày, ...). Nguồn xác thực phòng ban giống hệt
 * {@see \App\Support\Project\ProjectVisibility} — đừng cài lại thuật toán ở
 * nơi khác, luôn gọi qua lớp này.
 */
final class DepartmentScope
{
    /**
     * Danh sách id Department (local) của tài khoản, suy từ nhân viên gắn với
     * account đó — meta HRM (department_code/department_name) hợp với pivot
     * department_member đang active.
     *
     * @return list<int>
     */
    public static function idsForAccount(SystemAccount $account): array
    {
        $account->loadMissing('employee.departments');

        return self::idsForEmployee($account->employee);
    }

    /**
     * @return list<int>
     */
    public static function idsForEmployee(?Employee $employee): array
    {
        if ($employee === null) {
            return [];
        }

        $employee->loadMissing('departments');

        $ids = [];
        foreach ($employee->departments as $department) {
            $active = $department->pivot?->getAttribute('is_active');
            if ($active === false || $active === 0 || $active === '0') {
                continue;
            }
            $ids[] = (int) $department->id;
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];
        $code = trim((string) ($meta['department_code'] ?? ''));
        $name = trim((string) ($meta['department_name'] ?? $meta['department'] ?? ''));

        if ($code !== '') {
            $id = Department::query()->where('code', $code)->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        if ($name !== '') {
            $id = Department::query()->where('name', $name)->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Có được xem toàn bộ dữ liệu phòng ban hay không: admin-tier (bypass mọi
     * scoping) hoặc giữ quyền `department.view_scope` (manager/deputy_manager/
     * team_leader theo mặc định). member/viewer không bao giờ đúng ở đây.
     */
    public static function hasDepartmentWideScope(SystemAccount $account): bool
    {
        return $account->isAdminTier() || $account->allows('department.view_scope');
    }

    /**
     * Id nhân viên thuộc (các) phòng ban của tài khoản — hợp nhất khớp theo
     * meta HRM (department_code/department_name) và thành viên pivot
     * department_member, cùng cách khớp đã dùng ở
     * {@see \App\Support\Performance\PerformancePersonnelResolver::employeeIds()}.
     * Dùng cho các module cần scoping theo "nhân viên trong phòng ban của tôi"
     * (vd Báo cáo ngày) thay vì theo id Department (vd Project).
     *
     * @return list<int>
     */
    public static function employeeIdsInOwnDepartment(SystemAccount $account): array
    {
        $deptIds = self::idsForAccount($account);
        if ($deptIds === []) {
            return [];
        }

        $departments = Department::query()->whereIn('id', $deptIds)->get(['id', 'code', 'name']);

        $codes = $departments->pluck('code')->filter()->map(fn ($c) => trim((string) $c))->filter()->unique()->values()->all();
        $names = $departments->pluck('name')->filter()->map(fn ($n) => trim((string) $n))->filter()->unique()->values()->all();

        $fromMeta = collect();
        if ($codes !== [] || $names !== []) {
            $fromMeta = Employee::query()
                ->where('is_active', true)
                ->where(function ($q) use ($codes, $names) {
                    if ($codes !== []) {
                        $q->whereIn('meta->department_code', $codes)
                            ->orWhereIn('meta->unit_code', $codes);
                    }
                    foreach ($names as $name) {
                        $q->orWhere('meta->department_name', $name);
                    }
                })
                ->pluck('id');
        }

        $fromPivot = Department::query()
            ->whereIn('id', $deptIds)
            ->get()
            ->flatMap(fn (Department $d) => $d->members()->pluck('employees.id'));

        return $fromMeta->merge($fromPivot)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
