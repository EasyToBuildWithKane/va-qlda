<?php

namespace App\Policies;

use App\Models\OrgTeam;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class OrgTeamPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, OrgTeam $orgTeam): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return $this->canManage($account);
    }

    public function update(SystemAccount $account, OrgTeam $orgTeam): bool
    {
        return $this->canManage($account);
    }

    public function delete(SystemAccount $account, OrgTeam $orgTeam): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        return $this->canManage($account) && $orgTeam->level > 1;
    }

    private function canManage(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }
}
