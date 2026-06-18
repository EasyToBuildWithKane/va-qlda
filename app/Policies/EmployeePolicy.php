<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\SystemAccount;

/**
 * Who may see and edit employee profiles.
 *
 *   viewAny / view — every authenticated account (directory + member profile).
 *   update         — admin, or the person editing their own profile.
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

    public function update(SystemAccount $account, Employee $employee): bool
    {
        return $this->isSelf($account, $employee) || $account->allows('employee.update');
    }

    private function isSelf(SystemAccount $account, Employee $employee): bool
    {
        return $account->employee_id !== null && $account->employee_id === $employee->id;
    }
}
