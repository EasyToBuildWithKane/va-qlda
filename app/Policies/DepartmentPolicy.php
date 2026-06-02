<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

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
        return $this->isReviewer($account);
    }

    public function update(SystemAccount $account, Department $department): bool
    {
        return $this->isReviewer($account);
    }

    public function delete(SystemAccount $account, Department $department): bool
    {
        return $account->role === SystemRole::Admin;
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }
}
