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
            'avatar_path' => self::nullableString($payload['avatar_path'] ?? null),
            'role_title' => self::resolveRoleTitle($payload),
            'join_date' => self::nullableString($payload['hired_at'] ?? null),
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
            ?? self::nullableString($payload['job_position'] ?? null);
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

        $meta = array_filter([
            'department_name' => self::nullableString($payload['department_name'] ?? null)
                ?? self::nullableString(data_get($assignment, 'org_unit.name')),
            'company_name' => self::nullableString(data_get($assignment, 'company.name')),
            'position_name' => self::nullableString($payload['job_title_name'] ?? null)
                ?? self::nullableString(data_get($assignment, 'position.name')),
            'job_position' => self::nullableString($payload['job_position'] ?? null),
            'hrm_status' => self::nullableString($payload['status'] ?? null),
            'terminated_at' => self::nullableString($payload['terminated_at'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        return $meta === [] ? null : $meta;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
