<?php

namespace App\Support\Profile;

use App\Models\Employee;
use Illuminate\Support\Str;

/**
 * Derives a seniority badge ({value, label, color}) for an employee.
 *
 * Source of truth, in order of precedence:
 *   1. employees.meta['level']  — explicit ("junior"|"middle"|"senior"|"lead"|"manager")
 *   2. keywords inferred from role_title (e.g. "Senior Laravel Developer")
 *   3. fallback → "member"
 *
 * Kept derivation-only (no dedicated table yet) so the profile MVP renders from
 * data that already exists. Promote to a stored column when the Talent module lands.
 */
class Seniority
{
    /** @var array<string, array{label:string, color:string, rank:int}> */
    private const LEVELS = [
        'intern' => ['label' => 'Thực tập', 'color' => 'slate', 'rank' => 0],
        'junior' => ['label' => 'Junior', 'color' => 'sky', 'rank' => 1],
        'middle' => ['label' => 'Middle', 'color' => 'teal', 'rank' => 2],
        'senior' => ['label' => 'Senior', 'color' => 'violet', 'rank' => 3],
        'lead' => ['label' => 'Lead', 'color' => 'amber', 'rank' => 4],
        'manager' => ['label' => 'Manager', 'color' => 'rose', 'rank' => 5],
        'member' => ['label' => 'Thành viên', 'color' => 'slate', 'rank' => 1],
    ];

    /**
     * @return array{value:string, label:string, color:string, rank:int}
     */
    public static function for(Employee $employee): array
    {
        $meta = $employee->meta ?? [];
        $explicit = is_array($meta) ? Str::lower((string) ($meta['level'] ?? '')) : '';

        $value = isset(self::LEVELS[$explicit]) ? $explicit : self::infer($employee->role_title);
        $def = self::LEVELS[$value];

        return [
            'value' => $value,
            'label' => $def['label'],
            'color' => $def['color'],
            'rank' => $def['rank'],
        ];
    }

    private static function infer(?string $roleTitle): string
    {
        $title = Str::lower($roleTitle ?? '');

        return match (true) {
            $title === '' => 'member',
            str_contains($title, 'manager') || str_contains($title, 'quản lý') || str_contains($title, 'trưởng phòng') => 'manager',
            str_contains($title, 'lead') || str_contains($title, 'trưởng nhóm') || str_contains($title, 'head') => 'lead',
            str_contains($title, 'senior') || str_contains($title, 'sr.') => 'senior',
            str_contains($title, 'middle') || str_contains($title, 'mid ') => 'middle',
            str_contains($title, 'junior') || str_contains($title, 'jr.') => 'junior',
            str_contains($title, 'intern') || str_contains($title, 'thực tập') => 'intern',
            default => 'member',
        };
    }
}
