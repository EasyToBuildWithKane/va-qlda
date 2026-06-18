<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\SystemAccount;
use App\Support\Enums\CredentialPermission;

class CredentialPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->is_active;
    }

    public function view(SystemAccount $account, Credential $credential): bool
    {
        if ($account->isAdminTier()) {
            return true;
        }

        if ($credential->owner_id === $account->id || $credential->created_by === $account->id) {
            return true;
        }

        return $credential->grantFor($account) !== null;
    }

    public function create(SystemAccount $account): bool
    {
        return $account->allows('credential.create');
    }

    public function update(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.update')) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Edit);
    }

    public function delete(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.delete')) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Delete);
    }

    public function viewPassword(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.view_password')) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::CopyPassword);
    }

    public function share(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.share')) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Share);
    }

    public function manageAccess(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.manage_access')) {
            return true;
        }

        return $credential->created_by === $account->id
            || $credential->owner_id === $account->id;
    }

    public function viewAccessTab(SystemAccount $account, Credential $credential): bool
    {
        if ($account->isAdminTier() || $account->allows('credential.manage_access')) {
            return true;
        }

        if ($credential->created_by === $account->id || $credential->owner_id === $account->id) {
            return true;
        }

        return $credential->grantFor($account) !== null;
    }

    public function export(SystemAccount $account, Credential $credential): bool
    {
        if ($account->allows('credential.export')) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Export)
            || $credential->owner_id === $account->id;
    }
}
