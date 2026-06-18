<?php

namespace App\Policies;

use App\Models\CongngheSoftwareProposal;
use App\Models\SystemAccount;

class CongngheSoftwareProposalPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('congnghe.manage_proposals');
    }

    public function view(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        return $this->viewAny($account) || $this->isOwner($account, $proposal);
    }

    /** Cổng «Đề xuất đã gửi» — chỉ người gửi, không dùng luồng quản trị. */
    public function viewAsSubmitter(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        return $this->isOwner($account, $proposal);
    }

    private function isOwner(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        if ($proposal->system_account_id === $account->id) {
            return true;
        }

        $employeeEmail = strtolower(trim((string) ($account->employee?->email ?? '')));
        if ($employeeEmail === '') {
            return false;
        }

        return strtolower(trim($proposal->submitter_email)) === $employeeEmail;
    }

    public function update(SystemAccount $account, CongngheSoftwareProposal $proposal): bool
    {
        return $this->viewAny($account);
    }
}
