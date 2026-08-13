<?php

namespace App\Support\Project;

use App\Models\Department;
use App\Support\Options\DepartmentOptions;

/**
 * Chuẩn hoá phòng phụ trách + phòng liên đới trước khi validate/lưu.
 */
final class ProjectDepartmentPayload
{
    /**
     * @return array{department_id: int|null, scope_departments: list<int>}
     */
    public static function normalize(mixed $departmentId, mixed $related, bool $fallbackOwner = false): array
    {
        $owner = self::normalizeId($departmentId);
        $options = app(DepartmentOptions::class);

        if ($owner !== null && ! $options->isActiveDepartmentId($owner)) {
            $owner = null;
        }

        if ($owner === null && $fallbackOwner) {
            $owner = $options->defaultOwnerId();
        }

        $relatedIds = [];
        if (is_array($related)) {
            foreach ($related as $id) {
                $id = self::normalizeId($id);
                if ($id === null || $id === $owner) {
                    continue;
                }
                $relatedIds[] = $id;
            }
            $relatedIds = array_values(array_unique($relatedIds));
            if ($relatedIds !== []) {
                $active = Department::active()->whereKey($relatedIds)->pluck('id')->all();
                $relatedIds = array_values(array_map('intval', $active));
            }
        }

        return [
            'department_id' => $owner,
            'scope_departments' => $relatedIds,
        ];
    }

    private static function normalizeId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
