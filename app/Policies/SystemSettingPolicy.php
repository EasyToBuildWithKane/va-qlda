<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

/**
 * System configuration is admin-only. Both abilities are class-level
 * (no model instance) — authorize with the class:
 *
 *   $this->authorize('viewAny', SystemSetting::class);
 *   $this->authorize('manage', SystemSetting::class);
 */
class SystemSettingPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->role === SystemRole::Admin;
    }

    public function manage(SystemAccount $account): bool
    {
        return $account->role === SystemRole::Admin;
    }
}
