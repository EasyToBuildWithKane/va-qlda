<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Models\Vendor;
use App\Support\Enums\SystemRole;

class VendorPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->hasRole(SystemRole::Admin, SystemRole::Lead, SystemRole::Viewer);
    }

    public function view(SystemAccount $account, Vendor $vendor): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $this->manages($account);
    }

    public function update(SystemAccount $account, Vendor $vendor): bool
    {
        return $this->manages($account);
    }

    public function delete(SystemAccount $account, Vendor $vendor): bool
    {
        return $this->manages($account);
    }

    private function manages(SystemAccount $account): bool
    {
        return $account->hasRole(SystemRole::Admin, SystemRole::Lead);
    }
}
