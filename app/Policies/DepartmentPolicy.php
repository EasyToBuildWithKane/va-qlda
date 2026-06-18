<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\SystemAccount;

class DepartmentPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Department $department): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('department.create');
    }

    public function update(SystemAccount $account, Department $department): bool
    {
        return $account->allows('department.update');
    }

    public function delete(SystemAccount $account, Department $department): bool
    {
        return $account->allows('department.delete');
    }
}
