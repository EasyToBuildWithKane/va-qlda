<?php

namespace App\Policies;

use App\Models\Blocker;
use App\Models\SystemAccount;

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
        return $account->allows('blocker.create');
    }

    public function comment(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->allows('blocker.comment');
    }

    public function update(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->allows('blocker.update')
            || $this->isOwnerOrRaiser($account, $blocker);
    }

    public function delete(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->allows('blocker.delete');
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

    private function isOwnerOrRaiser(SystemAccount $account, Blocker $blocker): bool
    {
        return $account->employee_id !== null
            && in_array($account->employee_id, [$blocker->owner_id, $blocker->raised_by_id], true);
    }
}
