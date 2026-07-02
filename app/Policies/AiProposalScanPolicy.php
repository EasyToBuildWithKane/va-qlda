<?php

namespace App\Policies;

use App\Models\AiProposalScan;
use App\Models\SystemAccount;
use App\Support\Enums\AiProposalScanStatus;

class AiProposalScanPolicy
{
    public function create(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, AiProposalScan $scan): bool
    {
        return $scan->created_by === $account->id
            || $account->allows('ai_proposal.review');
    }

    public function update(SystemAccount $account, AiProposalScan $scan): bool
    {
        if ($scan->status === AiProposalScanStatus::Confirmed) {
            return false;
        }

        return $this->view($account, $scan);
    }

    public function confirm(SystemAccount $account, AiProposalScan $scan): bool
    {
        return $scan->status === AiProposalScanStatus::NeedsReview
            && $this->view($account, $scan);
    }
}
