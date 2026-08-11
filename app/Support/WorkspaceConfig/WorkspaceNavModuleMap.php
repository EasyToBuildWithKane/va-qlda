<?php

namespace App\Support\WorkspaceConfig;

use App\Support\Navigation;

/**
 * Maps main-app sidebar nav group keys → workspace-config catalog module keys.
 *
 * Used so global /settings/menu hides (and per-department enabled_nav_groups)
 * also filter modules on /workspace-config hub & shell.
 */
final class WorkspaceNavModuleMap
{
    /**
     * Nav group key → catalog module keys that hide when that group is disabled.
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        'daily' => ['daily_report_scoring'],
        'performance' => ['evaluation', 'evaluation_templates', 'evaluation_forms'],
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return self::MAP;
    }

    /**
     * Nav group key that controls a catalog module, or null if unmapped.
     */
    public static function navGroupForModule(string $moduleKey): ?string
    {
        foreach (self::MAP as $navKey => $modules) {
            if (\in_array($moduleKey, $modules, true)) {
                return $navKey;
            }
        }

        return null;
    }

    /**
     * Globally hidden nav groups (protected keys stripped — same as Navigation::for).
     *
     * @return list<string>
     */
    public static function globallyHiddenNavGroups(): array
    {
        return array_values(array_diff(
            array_map('strval', (array) config('va.menu_hidden_groups', [])),
            Navigation::PROTECTED_GROUP_KEYS,
        ));
    }

    /**
     * Whether a catalog module should be visible given global + optional department filters.
     *
     * @param  list<string>|null  $enabledNavGroups  null = no department restriction
     */
    public static function isModuleVisible(string $moduleKey, ?array $enabledNavGroups = null): bool
    {
        $navKey = self::navGroupForModule($moduleKey);
        if ($navKey === null) {
            return true;
        }

        if (\in_array($navKey, self::globallyHiddenNavGroups(), true)) {
            return false;
        }

        if ($enabledNavGroups !== null && ! \in_array($navKey, $enabledNavGroups, true)) {
            return false;
        }

        return true;
    }
}
