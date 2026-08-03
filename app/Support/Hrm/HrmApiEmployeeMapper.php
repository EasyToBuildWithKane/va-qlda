<?php

namespace App\Support\Hrm;

/**
 * Map payload Employee API HRM → attributes va_prd_employees.
 *
 * @phpstan-type HrmApiEmployee array<string, mixed>
 */
final class HrmApiEmployeeMapper
{
    /**
     * @param  HrmApiEmployee  $payload
     * @return array<string, mixed>
     */
    public static function toEmployeeAttributes(array $payload): array
    {
        $email = self::resolveEmail($payload);
        $uuid = self::nullableString($payload['uuid'] ?? null);
        $legacyId = isset($payload['legacy_user_id']) && is_numeric($payload['legacy_user_id'])
            ? (int) $payload['legacy_user_id']
            : null;

        return [
            'hrm_employee_uuid' => $uuid,
            'hrm_user_id' => $legacyId !== null && $legacyId > 0 ? $legacyId : null,
            'code' => self::resolveCode($payload, $legacyId, $uuid),
            'full_name' => self::resolveFullName($payload, $email),
            'email' => $email,
            'phone' => self::nullableString($payload['phone'] ?? null),
            'avatar_path' => self::resolveAvatarPath($payload),
            'role_title' => self::resolveRoleTitle($payload),
            'join_date' => self::normalizeJoinDate($payload['hired_at'] ?? null),
            'is_active' => ($payload['status'] ?? null) === 'active',
            'meta' => self::buildMeta($payload),
        ];
    }

    /**
     * @param  HrmApiEmployee  $payload
     */
    private static function resolveEmail(array $payload): ?string
    {
        foreach (['company_email', 'personal_email'] as $key) {
            $email = self::nullableString($payload[$key] ?? null);
            if ($email !== null) {
                return strtolower($email);
            }
        }

        return null;
    }

    /**
     * @param  HrmApiEmployee  $payload
     */
    private static function resolveFullName(array $payload, ?string $email): string
    {
        $name = self::nullableString($payload['full_name'] ?? null);
        if ($name !== null) {
            return $name;
        }

        return $email ?? 'Nhân sự HRM';
    }

    /**
     * @param  HrmApiEmployee  $payload
     */
    private static function resolveCode(array $payload, ?int $legacyId, ?string $uuid): string
    {
        $code = self::nullableString($payload['code'] ?? null);
        if ($code !== null) {
            return $code;
        }

        if ($legacyId !== null && $legacyId > 0) {
            return 'HRM-'.str_pad((string) $legacyId, 6, '0', STR_PAD_LEFT);
        }

        if ($uuid !== null) {
            return 'HRM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 8));
        }

        return 'HRM-UNKNOWN';
    }

    /**
     * @param  HrmApiEmployee  $payload
     */
    private static function resolveRoleTitle(array $payload): ?string
    {
        return self::nullableString($payload['job_title_name'] ?? null)
            ?? self::positionTitle($payload['primary_assignment'] ?? null)
            ?? self::nullableString($payload['job_position'] ?? null);
    }

    /**
     * Avatar từ HRM (URL tuyệt đối /avatars/{id} hoặc CDN). Null khi thiếu — không ghi đè ảnh Workspace local.
     *
     * @param  HrmApiEmployee  $payload
     */
    private static function resolveAvatarPath(array $payload): ?string
    {
        return self::nullableString($payload['avatar_path'] ?? null)
            ?? self::nullableString($payload['avatar_url'] ?? null);
    }

    /**
     * @param  HrmApiEmployee  $payload
     * @return array<string, mixed>|null
     */
    private static function buildMeta(array $payload): ?array
    {
        $assignment = is_array($payload['primary_assignment'] ?? null)
            ? $payload['primary_assignment']
            : [];

        $orgUnit = is_array($assignment['org_unit'] ?? null) ? $assignment['org_unit'] : [];
        $company = is_array($assignment['company'] ?? null) ? $assignment['company'] : [];
        $orgType = self::nullableString($orgUnit['type'] ?? null);
        $orgName = self::nullableString($orgUnit['name'] ?? null);

        $departmentName = self::nullableString($payload['department_name'] ?? null);
        $unitName = null;
        $headquarterName = self::resolveHeadquarterName($payload, $assignment, $orgType, $orgName);

        if ($orgType === 'unit') {
            $unitName = $orgName;
        } elseif ($orgType === 'department') {
            $departmentName = $departmentName ?? $orgName;
        } elseif (! in_array($orgType, ['headquarter', 'branch'], true)) {
            $departmentName = $departmentName ?? $orgName;
        }

        $meta = array_filter([
            'department_name' => $departmentName,
            'department_code' => self::nullableString($orgUnit['code'] ?? null),
            'company_name' => self::nullableString($company['name'] ?? null),
            'company_id' => self::nullableString($company['code'] ?? null),
            'unit_name' => $unitName,
            'headquarter_name' => $headquarterName,
            'workplace' => self::nullableString($payload['workplace'] ?? null),
            'position_name' => self::nullableString($payload['job_title_name'] ?? null)
                ?? self::positionTitle($assignment)
                ?? self::nullableString($payload['job_position'] ?? null),
            'concurrent_position_name' => self::concurrentPositionLabel($payload),
            'job_position' => self::nullableString($payload['job_position'] ?? null),
            'hrm_status' => self::nullableString($payload['status'] ?? null),
            'terminated_at' => self::nullableString($payload['terminated_at'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        return $meta === [] ? null : $meta;
    }

    /**
     * Trụ sở / cơ sở: ưu tiên branch → headquarter từ tổ tiên assignment,
     * rồi org_unit type headquarter|branch, cuối cùng workplace trên hồ sơ HRM.
     *
     * @param  HrmApiEmployee  $payload
     * @param  array<string, mixed>  $assignment
     */
    private static function resolveHeadquarterName(
        array $payload,
        array $assignment,
        ?string $orgType,
        ?string $orgName,
    ): ?string {
        $branch = self::namedRef($assignment['branch'] ?? null);
        if ($branch !== null) {
            return $branch;
        }

        $hq = self::namedRef($assignment['headquarter'] ?? null);
        if ($hq !== null) {
            return $hq;
        }

        if (in_array($orgType, ['headquarter', 'branch'], true)) {
            return $orgName;
        }

        return self::nullableString($payload['workplace'] ?? null);
    }

    private static function namedRef(mixed $ref): ?string
    {
        if (! is_array($ref)) {
            return null;
        }

        return self::nullableString($ref['name'] ?? null);
    }

    /**
     * @param  array<string, mixed>|null  $assignment
     */
    private static function positionTitle(mixed $assignment): ?string
    {
        if (! is_array($assignment)) {
            return null;
        }

        $position = is_array($assignment['position'] ?? null) ? $assignment['position'] : [];

        return self::nullableString($position['title'] ?? null)
            ?? self::nullableString($position['name'] ?? null);
    }

    /**
     * @param  HrmApiEmployee  $payload
     */
    private static function concurrentPositionLabel(array $payload): ?string
    {
        $rows = $payload['concurrent_assignments'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $labels = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = self::positionTitle($row);
            $unit = self::nullableString(data_get($row, 'org_unit.name'));
            if ($title === null && $unit === null) {
                continue;
            }
            $labels[] = $unit !== null && $title !== null
                ? "{$title} · {$unit}"
                : ($title ?? $unit);
        }

        $labels = array_values(array_unique(array_filter($labels)));

        return $labels === [] ? null : implode('; ', $labels);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /** Chuẩn hoá hired_at HRM → Y-m-d (tránh cast date Laravel lỗi chuỗi lạ). */
    private static function normalizeJoinDate(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m) === 1) {
            return $m[1];
        }

        $ts = strtotime($raw);

        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
