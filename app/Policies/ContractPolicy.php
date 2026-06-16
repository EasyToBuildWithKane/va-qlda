<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

/**
 * CLM — Admin toàn quyền; Lead quản lý hợp đồng; Viewer chỉ xem (read-only).
 * Member không truy cập module.
 */
class ContractPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->hasRole(SystemRole::Admin, SystemRole::Lead, SystemRole::Viewer);
    }

    public function view(SystemAccount $account, Contract $contract): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $this->manages($account);
    }

    public function update(SystemAccount $account, Contract $contract): bool
    {
        return $this->manages($account);
    }

    public function delete(SystemAccount $account, Contract $contract): bool
    {
        return $this->manages($account);
    }

    public function manage(SystemAccount $account): bool
    {
        return $this->manages($account);
    }

    public function import(SystemAccount $account): bool
    {
        return $this->manages($account);
    }

    private function manages(SystemAccount $account): bool
    {
        return $account->hasRole(SystemRole::Admin, SystemRole::Lead);
    }
}
