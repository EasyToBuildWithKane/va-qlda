<?php

namespace App\Policies;

use App\Models\Blocker;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class BlockerPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, Blocker $blocker): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->role !== SystemRole::Viewer;
    }

    public function comment(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->role !== SystemRole::Viewer;
    }

    public function update(SystemAccount $account, Blocker $blocker): bool
    {
        return $this->isReviewer($account)
            || $this->isOwnerOrRaiser($account, $blocker);
    }

    public function delete(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->role === SystemRole::Admin
            || $this->isReviewer($account);
    }

    public function recheck(SystemAccount $account, Blocker $blocker): bool
    {
        return $this->isRaiser($account, $blocker);
    }

    private function isRaiser(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $blocker->raised_by_id;
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    private function isOwnerOrRaiser(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->employee_id !== null
            && in_array($account->employee_id, [$blocker->owner_id, $blocker->raised_by_id], true);
    }
}
