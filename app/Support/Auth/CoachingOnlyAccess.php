<?php

namespace App\Support\Auth;

use App\Models\SystemAccount;
use App\Providers\RouteServiceProvider;

/**
 * Google login exceptions (config va.google_allowed_emails): single-email whitelist,
 * not whole @gmail.com. Those accounts only see and access the Coaching module.
 */
final class CoachingOnlyAccess
{
    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        $raw = config('va.google_allowed_emails', []);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            $raw,
        ))));
    }

    public static function isCoachingOnlyEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return in_array(strtolower(trim($email)), self::emails(), true);
    }

    public static function appliesTo(SystemAccount $account): bool
    {
        return self::isCoachingOnlyEmail($account->employee?->email);
    }

    public static function homePath(SystemAccount $account): string
    {
        return self::appliesTo($account)
            ? route('coaching.dashboard', [], false)
            : RouteServiceProvider::HOME;
    }

    public static function googleEmailAllowed(string $email): bool
    {
        return in_array(strtolower(trim($email)), self::emails(), true);
    }
}
