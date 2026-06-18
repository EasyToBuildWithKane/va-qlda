<?php

namespace App\Support\Enums;

/**
 * Application roles for simulated auth. Intentionally simple — will map to
 * SSO/enterprise roles later. See the permission matrix in the system spec.
 */
enum SystemRole: string
{
    case SuperAdmin = 'super_admin'; // Cấu hình hệ thống + phân quyền + thao tác nguy hiểm
    case Admin = 'admin';            // Full nghiệp vụ (không cấu hình/phân quyền)
    case Lead = 'lead';              // Manages team + reviews reports
    case Member = 'member';          // Creates reports, sees own projects
    case Viewer = 'viewer';          // Read-only (e.g. board of directors)

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Administrator',
            self::Lead => 'Team Lead',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'fuchsia',
            self::Admin => 'rose',
            self::Lead => 'violet',
            self::Member => 'sky',
            self::Viewer => 'slate',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
