<?php

namespace App\Support\Enums;

/**
 * Application roles for simulated auth. Intentionally simple — will map to
 * SSO/enterprise roles later. See the permission matrix in the system spec.
 */
enum SystemRole: string
{
    case Admin = 'admin';   // Full control
    case Lead = 'lead';     // Manages team + reviews reports
    case Member = 'member'; // Creates reports, sees own projects
    case Viewer = 'viewer'; // Read-only (e.g. board of directors)

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Lead => 'Team Lead',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
