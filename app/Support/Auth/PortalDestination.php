<?php

namespace App\Support\Auth;

use App\Models\SystemAccount;
use App\Providers\RouteServiceProvider;

/**
 * Post-login landing path, resolved by the portal a user signed in through.
 *
 *   - Cổng /tech/login (whitelist)  → công cụ QLDA (/dashboard).
 *   - Cổng /login (portal, all org) → /dashboard khi landing CN bị ẩn tạm;
 *     khi `va.congnghe_landing_public` = true → /congnghe (hoặc /demo_1).
 */
final class PortalDestination
{
    public static function homePath(SystemAccount $account, string $portal = 'portal'): string
    {
        if ($portal === 'tech') {
            return RouteServiceProvider::HOME;
        }

        // Tạm thời: portal login vào QLDA; không đưa user vào landing demo.
        if (! config('va.congnghe_landing_public')) {
            return RouteServiceProvider::HOME;
        }

        return route('congnghe', [], false);
    }

    /**
     * Có thể rời landing /congnghe để vào công cụ QLDA (dashboard, dự án, …).
     */
    public static function canEnterQlda(SystemAccount $account): bool
    {
        if (TechLoginAccess::isAllowedEmail($account->employee?->email)) {
            return true;
        }

        return $account->isAdminTier();
    }

    /**
     * URL đích khi bấm «Vào hệ thống» từ landing.
     */
    public static function qldaHomePath(SystemAccount $account): string
    {
        return RouteServiceProvider::HOME;
    }
}
