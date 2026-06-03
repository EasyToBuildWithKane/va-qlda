<?php

namespace App\Policies;

use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;

class AiPurchaseProposalPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function create(SystemAccount $account): bool
    {
        return true;
    }

    public function review(SystemAccount $account, AiPurchaseProposal $proposal): bool
    {
        return $account->role === SystemRole::Admin
            && $proposal->status === AiPurchaseProposalStatus::Pending;
    }
}
