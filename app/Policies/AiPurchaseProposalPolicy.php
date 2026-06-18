<?php

namespace App\Policies;

use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiPurchaseProposalStatus;

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

    public function update(SystemAccount $account, AiPurchaseProposal $proposal): bool
    {
        if ($proposal->status !== AiPurchaseProposalStatus::Pending) {
            return false;
        }

        if ($account->allows('ai_proposal.update')) {
            return true;
        }

        return $proposal->created_by === $account->id;
    }

    public function review(SystemAccount $account, AiPurchaseProposal $proposal): bool
    {
        return $account->allows('ai_proposal.review')
            && $proposal->status === AiPurchaseProposalStatus::Pending;
    }

    public function delete(SystemAccount $account, AiPurchaseProposal $proposal): bool
    {
        if (in_array($proposal->status, [
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ], true)) {
            return false;
        }

        if ($account->allows('ai_proposal.delete')) {
            return true;
        }

        return $proposal->created_by === $account->id
            && in_array($proposal->status, [
                AiPurchaseProposalStatus::Draft,
                AiPurchaseProposalStatus::Rejected,
            ], true);
    }
}
