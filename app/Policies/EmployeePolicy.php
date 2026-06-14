<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

/**
 * Who may see profiles and the performance data behind them.
 *
 *   viewAny / view      — every authenticated account (the member directory and
 *                         a person's identity + skills are open within the org).
 *   viewPerformance     — admin/lead (managers) or the person themselves. Gates
 *                         the stats strip, project history and activity feed.
 *   update              — admin, or the person editing their own profile.
 */
class EmployeePolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Employee $employee): bool
    {
        return true;
    }

    public function viewPerformance(SystemAccount $account, Employee $employee): bool
    {
        return $this->isSelf($account, $employee)
            || in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    public function update(SystemAccount $account, Employee $employee): bool
    {
        return $this->isSelf($account, $employee) || $account->role === SystemRole::Admin;
    }

    private function isSelf(SystemAccount $account, Employee $employee): bool
    {
        return $account->employee_id !== null && $account->employee_id === $employee->id;
    }
}
