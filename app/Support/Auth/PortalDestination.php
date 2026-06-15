<?php

namespace App\Support\Auth;

use App\Models\SystemAccount;
use App\Providers\RouteServiceProvider;
use App\Support\Enums\SystemRole;

/**
 * Post-login landing path, resolved by the portal a user signed in through.
 *
 *   - Coaching-only accounts        → luôn về dashboard coaching.
 *   - Cổng /tech/login (whitelist)  → công cụ QLDA (/dashboard).
 *   - Cổng /login (portal, all org) → trang giới thiệu Phòng Công Nghệ (/congnghe).
 *
 * Coaching check is delegated to {@see CoachingOnlyAccess} so the special-case
 * rule lives in one place.
 */
final class PortalDestination
{
    public static function homePath(SystemAccount $account, string $portal = 'portal'): string
    {
        if (CoachingOnlyAccess::appliesTo($account)) {
            return route('coaching.dashboard', [], false);
        }

        return $portal === 'tech'
            ? RouteServiceProvider::HOME
            : route('congnghe', [], false);
    }

    /**
     * Có thể rời landing /congnghe để vào công cụ QLDA (dashboard, dự án, …).
     */
    public static function canEnterQlda(SystemAccount $account): bool
    {
        if (CoachingOnlyAccess::appliesTo($account)) {
            return true;
        }

        if (TechLoginAccess::isAllowedEmail($account->employee?->email)) {
            return true;
        }

        return $account->role === SystemRole::Admin;
    }

    /**
     * URL đích khi bấm «Vào hệ thống» từ landing.
     */
    public static function qldaHomePath(SystemAccount $account): string
    {
        return CoachingOnlyAccess::homePath($account);
    }
}
