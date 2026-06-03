<?php

namespace App\Support\Auth;

use App\Support\Enums\SystemRole;

/**
 * Central permission checks for system_accounts.role (see config/va_permissions.php).
 */
final class Permissions
{
    public static function roleAllows(SystemRole $role, string $permission): bool
    {
        /** @var array<string, array<int, string>> $grants */
        $grants = config('va_permissions.role_grants', []);

        $forRole = $grants[$role->value] ?? [];

        if (in_array('*', $forRole, true)) {
            return true;
        }

        return in_array($permission, $forRole, true);
    }

    /** @return array<int, string> */
    public static function permissionKeys(): array
    {
        return array_keys(config('va_permissions.permissions', []));
    }
}
