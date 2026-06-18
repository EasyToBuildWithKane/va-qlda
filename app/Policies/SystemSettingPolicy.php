<?php

namespace App\Policies;

use App\Models\SystemAccount;

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
        return $account->allows('system.settings.view');
    }

    public function manage(SystemAccount $account): bool
    {
        return $account->allows('system.settings.manage');
    }
}
