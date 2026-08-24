<?php

namespace App\Support\WorkspaceConfig;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\Profile\ProfileOrgRelations;

/**
 * Resolves the caller's HRM/local department and whether they may open a workspace.
 */
final class WorkspaceScopeResolver
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
    ) {}

    /**
     * Phòng ban của tài khoản: mã + tên (HRM meta → pivot Workspace → danh mục).
     *
     * @return array{code: string, name: string}|null
     */
    public function ownDepartment(SystemAccount $account): ?array
    {
        $account->loadMissing('employee.departments');

        $employee = $account->employee;
        if ($employee === null) {
            return null;
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];

        $metaCode = trim((string) ($meta['department_code'] ?? ''));
        if ($metaCode !== '') {
            return $this->hydrateDepartment($metaCode, $meta);
        }

        $fromPivot = $this->departmentFromPivot($employee);
        if ($fromPivot !== null) {
            return $fromPivot;
        }

        $fromName = $this->departmentFromName($meta);
        if ($fromName !== null) {
            return $fromName;
        }

        $unitCode = trim((string) ($meta['unit_code'] ?? ''));
        if ($unitCode !== '') {
            $unit = $this->departments->findByCode($unitCode);
            $parent = trim((string) ($unit['parent_code'] ?? ''));
            if ($parent !== '') {
                return $this->hydrateDepartment($parent, $meta);
            }
            if ($unit !== null) {
                $unitName = trim((string) ($unit['name'] ?? ''));

                return [
                    'code' => $unit['code'],
                    'name' => $unitName !== '' ? $unitName : $unit['code'],
                ];
            }
        }

        return null;
    }

    /**
     * Stable department_code for the account (HRM meta preferred).
     */
    public function ownDepartmentCode(SystemAccount $account): ?string
    {
        $own = $this->ownDepartment($account);
        $code = trim((string) ($own['code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    public function canManageAll(SystemAccount $account): bool
    {
        return $account->allows('workspace.hub.manage');
    }

    public function canViewHub(SystemAccount $account): bool
    {
        return $account->allows('workspace.hub.view')
            || $account->allows('workspace.hub.manage');
    }

    /**
     * Super-admin / hub.manage may open any code; others only their own department.
     */
    public function canAccess(SystemAccount $account, ?string $departmentCode): bool
    {
        if (! $this->canViewHub($account)) {
            return false;
        }

        if ($this->canManageAll($account)) {
            return true;
        }

        $code = trim((string) $departmentCode);
        if ($code === '') {
            return false;
        }

        $own = $this->ownDepartmentCode($account);

        return $own !== null && strcasecmp($own, $code) === 0;
    }

    /**
     * Force department filter for non-managers; null = no forced filter (manager).
     */
    public function forcedDepartmentCode(SystemAccount $account): ?string
    {
        if ($this->canManageAll($account)) {
            return null;
        }

        return $this->ownDepartmentCode($account);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{code: string, name: string}
     */
    private function hydrateDepartment(string $code, array $meta): array
    {
        $row = $this->departments->findByCode($code);
        $name = trim((string) ($row['name'] ?? $meta['department_name'] ?? $meta['department'] ?? ''));

        return [
            'code' => $row['code'] ?? $code,
            'name' => $name !== '' ? $name : $code,
        ];
    }

    /**
     * @return array{code: string, name: string}|null
     */
    private function departmentFromPivot(Employee $employee): ?array
    {
        $dept = $employee->departments
            ->filter(function (Department $d): bool {
                $active = $d->pivot?->getAttribute('is_active');

                return $active !== false && $active !== 0 && $active !== '0';
            })
            ->first();

        if ($dept === null) {
            return null;
        }

        $code = trim((string) $dept->code);
        if ($code === '') {
            return null;
        }

        $row = $this->departments->findByCode($code);

        return [
            'code' => $row['code'] ?? $code,
            'name' => $row['name'] ?? $dept->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{code: string, name: string}|null
     */
    private function departmentFromName(array $meta): ?array
    {
        $name = trim((string) ($meta['department_name'] ?? $meta['department'] ?? ''));
        if ($name === '') {
            return null;
        }

        $row = $this->departments->findByName($name);
        if ($row !== null) {
            return [
                'code' => $row['code'],
                'name' => $row['name'],
            ];
        }

        $resolved = ProfileOrgRelations::departmentCode($meta);
        if (! filled($resolved)) {
            return [
                'code' => '',
                'name' => $name,
            ];
        }

        return $this->hydrateDepartment((string) $resolved, $meta);
    }
}
