<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('vendor.view');
    }

    public function view(SystemAccount $account, Vendor $vendor): bool
    {
        return $this->viewAny($account);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('vendor.create');
    }

    public function update(SystemAccount $account, Vendor $vendor): bool
    {
        return $account->allows('vendor.update');
    }

    public function delete(SystemAccount $account, Vendor $vendor): bool
    {
        return $account->allows('vendor.delete');
    }
}
