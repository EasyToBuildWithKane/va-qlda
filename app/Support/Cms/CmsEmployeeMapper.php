<?php

namespace App\Support\Cms;

use App\Models\Cms\CmsUser;
use App\Models\Cms\CmsUserInfo;
use Carbon\CarbonInterface;

/**
 * Maps CMS user + HR profile into va_prd_employees attributes (no DB writes).
 */
final class CmsEmployeeMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toEmployeeAttributes(CmsUser $user, ?CmsUserInfo $info): array
    {
        $email = strtolower(trim($user->email));

        return [
            'cms_user_id' => $user->id,
            'code' => self::resolveCode($user->id, $info),
            'full_name' => trim($user->name) !== '' ? trim($user->name) : $email,
            'email' => $email !== '' ? $email : null,
            'phone' => self::nullableString($info?->phone),
            'avatar_path' => self::nullableString($user->avatar),
            'role_title' => self::resolveRoleTitle($info),
            'join_date' => self::resolveJoinDate($info?->start_working_date),
            'is_active' => $user->deleted_at === null,
            'meta' => self::buildMeta($info),
        ];
    }

    private static function resolveCode(int $cmsUserId, ?CmsUserInfo $info): string
    {
        $code = self::nullableString($info?->code);
        if ($code !== null) {
            return $code;
        }

        return 'CMS-'.str_pad((string) $cmsUserId, 6, '0', STR_PAD_LEFT);
    }

    private static function resolveRoleTitle(?CmsUserInfo $info): ?string
    {
        if ($info === null) {
            return null;
        }

        $primary = self::nullableString($info->position_name);
        if ($primary !== null) {
            return $primary;
        }

        return self::nullableString($info->concurrent_position_name);
    }

    private static function resolveJoinDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildMeta(?CmsUserInfo $info): ?array
    {
        if ($info === null) {
            return null;
        }

        $meta = array_filter([
            'department_id' => $info->department_id,
            'company_id' => $info->company_id,
            'department_name' => self::nullableString($info->department_name),
            'company_name' => self::nullableString($info->company_name),
            'unit_name' => self::nullableString($info->unit_name),
            'headquarter_name' => self::nullableString($info->headquarter_name),
            'position_name' => self::nullableString($info->position_name),
            'concurrent_position_name' => self::nullableString($info->concurrent_position_name),
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
