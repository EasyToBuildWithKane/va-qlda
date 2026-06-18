<?php

namespace App\Support\Auth;

use App\Support\Enums\SystemRole;

/**
 * Central permission checks for system_accounts.role (see config/va_permissions.php).
 *
 * Grant hierarchy (a coarser grant implies finer ones):
 *   '*'              → every permission
 *   '{module}.*'     → every ability in that module
 *   '{module}.{act}' → exactly that ability
 *
 * Note: '{module}.manage' is a *concrete* ability (e.g. ProjectPolicy::manage =
 * sprints/members), NOT a module wildcard — it must not imply delete. Use the
 * UI "Toàn quyền module" button (ticks all abilities) for blanket grants.
 */
final class Permissions
{
    public static function roleAllows(SystemRole $role, string $permission): bool
    {
        /** @var array<string, array<int, string>> $grants */
        $grants = config('va_permissions.role_grants', []);

        $forRole = $grants[$role->value] ?? [];

        if (\in_array('*', $forRole, true)) {
            return true;
        }

        if (\in_array($permission, $forRole, true)) {
            return true;
        }

        // Module wildcard ('{module}.*') implies every ability under it.
        if (str_contains($permission, '.')) {
            $module = strstr($permission, '.', true);

            if (\in_array("{$module}.*", $forRole, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public static function permissionKeys(): array
    {
        return PermissionCatalog::keys();
    }
}
