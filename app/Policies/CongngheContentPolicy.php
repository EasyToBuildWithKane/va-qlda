<?php

namespace App\Policies;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;

/**
 * Quản trị nội dung trang /congnghe là admin-only. Cả hai ability đều ở mức
 * class (không cần instance):
 *
 *   $this->authorize('viewAny', CongngheSection::class);
 *   $this->authorize('manage', CongngheSection::class);
 */
class CongngheContentPolicy
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
