<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\SystemAccount;

/**
 * CLM — Admin toàn quyền; Lead quản lý hợp đồng; Viewer chỉ xem (read-only).
 * Member không truy cập module.
 */
class ContractPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('contract.view');
    }

    public function view(SystemAccount $account, Contract $contract): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('contract.create');
    }

    public function update(SystemAccount $account, Contract $contract): bool
    {
        return $account->allows('contract.update');
    }

    public function delete(SystemAccount $account, Contract $contract): bool
    {
        return $account->allows('contract.delete');
    }

    public function manage(SystemAccount $account): bool
    {
        return $account->allows('contract.manage');
    }

    public function import(SystemAccount $account): bool
    {
        return $account->allows('contract.import');
    }

    public function export(SystemAccount $account): bool
    {
        return $account->allows('contract.export');
    }
}
