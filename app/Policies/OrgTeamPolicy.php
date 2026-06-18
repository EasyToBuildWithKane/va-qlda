<?php

namespace App\Policies;

use App\Models\OrgTeam;
use App\Models\SystemAccount;

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
        return $account->allows('org_team.create');
    }

    public function update(SystemAccount $account, OrgTeam $orgTeam): bool
    {
        return $account->allows('org_team.update');
    }

    public function delete(SystemAccount $account, OrgTeam $orgTeam): bool
    {
        // Admin tier may delete any node; finer grants only below the root.
        return $account->allows('org_team.delete')
            && ($account->isAdminTier() || $orgTeam->level > 1);
    }
}
