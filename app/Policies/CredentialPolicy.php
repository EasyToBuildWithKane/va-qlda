<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\SystemAccount;
use App\Support\Enums\CredentialPermission;
use App\Support\Enums\SystemRole;

class CredentialPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->is_active;
    }

    public function view(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($credential->owner_id === $account->id || $credential->created_by === $account->id) {
            return true;
        }

        return $credential->grantFor($account) !== null;
    }

    public function create(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead, SystemRole::Member], true);
    }

    public function update(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Edit);
    }

    public function delete(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Delete);
    }

    public function viewPassword(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::CopyPassword);
    }

    public function share(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        if ($credential->owner_id === $account->id) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Share);
    }

    public function manageAccess(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        return $credential->owner_id === $account->id;
    }

    public function export(SystemAccount $account, Credential $credential): bool
    {
        if ($account->role === SystemRole::Admin) {
            return true;
        }

        return $credential->hasPermission($account, CredentialPermission::Export)
            || $credential->owner_id === $account->id;
    }
}
