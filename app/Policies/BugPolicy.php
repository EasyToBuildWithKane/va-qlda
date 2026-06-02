<?php

namespace App\Policies;

use App\Models\Bug;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class BugPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Bug $bug): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->role !== SystemRole::Viewer;
    }

    public function update(SystemAccount $account, Bug $bug): bool
    {
        return $this->isReviewer($account) || $this->isAssignee($account, $bug);
    }

    public function delete(SystemAccount $account, Bug $bug): bool
    {
        return $account->role === SystemRole::Admin;
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    private function isAssignee(SystemAccount $account, Bug $bug): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $bug->assignee_id;
    }
}
