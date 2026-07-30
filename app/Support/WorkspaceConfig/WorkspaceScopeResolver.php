<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;
use App\Support\Profile\ProfileOrgRelations;

/**
 * Resolves the caller's HRM/local department and whether they may open a workspace.
 */
final class WorkspaceScopeResolver
{
    /**
     * Stable department_code for the account (HRM meta preferred).
     */
    public function ownDepartmentCode(SystemAccount $account): ?string
    {
        $account->loadMissing('employee');

        $employee = $account->employee;
        if ($employee === null) {
            return null;
        }

        $meta = is_array($employee->meta) ? $employee->meta : [];

        $fromMeta = trim((string) ($meta['department_code'] ?? ''));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        $resolved = ProfileOrgRelations::departmentCode($meta);

        return filled($resolved) ? $resolved : null;
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
}
