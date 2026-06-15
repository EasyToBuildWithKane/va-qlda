<?php

namespace App\Policies;

use App\Models\CongngheSoftwareProposal;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

class CongngheSoftwareProposalPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }

    public function view(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        return $this->viewAny($account);
    }

    public function update(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        return $this->viewAny($account);
    }
}
