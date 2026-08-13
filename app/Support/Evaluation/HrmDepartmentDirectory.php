<?php

namespace App\Support\Evaluation;

use App\Models\Department;
use App\Models\Employee;
use App\Services\Hrm\HrmApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Danh mục phòng ban cho cấu hình đánh giá.
 * Ưu tiên HRM Public API `GET /org-units` (ability `org:read`),
 * rồi merge local `departments` + distinct từ employees.meta.
 */
final class HrmDepartmentDirectory
{
    public const CACHE_KEY = 'evaluation.department_directory.v3';

    public const CACHE_TTL_SECONDS = 86400;

    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>
     */
    public function all(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        /** @var list<array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}|null
     */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        foreach ($this->all() as $row) {
            if (strcasecmp($row['code'], $code) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Phòng / khối (không gồm tổ/bộ phận).
     *
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>
     */
    public function departments(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $row) => ($row['type'] ?? 'department') !== 'unit',
        ));
    }

    /**
     * Tổ / bộ phận — có thể lọc theo mã phòng cha.
     *
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>
     */
    public function units(?string $parentCode = null): array
    {
        $parent = strtoupper(trim((string) $parentCode));

        return array_values(array_filter(
            $this->all(),
            function (array $row) use ($parent) {
                if (($row['type'] ?? '') !== 'unit') {
                    return false;
                }
                if ($parent === '') {
                    return true;
                }

                return strtoupper((string) ($row['parent_code'] ?? '')) === $parent;
            },
        ));
    }

    /**
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>
     */
    private function build(): array
    {
        /** @var array<string, array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}> $byCode */
        $byCode = [];

        $this->mergeLocalDepartments($byCode);
        $this->mergeHrmOrgUnits($byCode);
        $this->mergeEmployeeMeta($byCode);

        $list = array_values($byCode);
        usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * @param  array<string, array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>  $byCode
     */
    private function mergeLocalDepartments(array &$byCode): void
    {
        Department::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->each(function (Department $dept) use (&$byCode) {
                $code = trim((string) $dept->code);
                if ($code === '') {
                    return;
                }
                $byCode[strtoupper($code)] = [
                    'code' => $code,
                    'name' => $dept->name,
                    'local_department_id' => $dept->id,
                    'source' => 'local',
                    'type' => 'department',
                    'parent_code' => null,
                    'hrm_uuid' => null,
                ];
            });
    }

    /**
     * @param  array<string, array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>  $byCode
     */
    private function mergeHrmOrgUnits(array &$byCode): void
    {
        if (! $this->hrmApi->isConfigured()) {
            Log::info('evaluation.department_directory.hrm_api_skipped', [
                'reason' => 'HRM_API_BASE_URL / HRM_API_TOKEN chưa cấu hình',
            ]);

            return;
        }

        try {
            // Phòng/Khối + Tổ/Bộ phận (đủ danh mục PB dùng cho đánh giá)
            $units = array_merge(
                $this->hrmApi->listOrgUnits(['type' => 'department', 'per_page' => 100]),
                $this->hrmApi->listOrgUnits(['type' => 'unit', 'per_page' => 100]),
            );
        } catch (\Throwable $e) {
            Log::warning('evaluation.department_directory.hrm_api_failed', [
                'message' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($units as $unit) {
            if (($unit['status'] ?? 'active') !== 'active') {
                continue;
            }

            $code = trim((string) ($unit['code'] ?? ''));
            $name = trim((string) ($unit['name'] ?? ''));
            if ($code === '') {
                continue;
            }

            $key = strtoupper($code);
            $uuid = isset($unit['uuid']) ? (string) $unit['uuid'] : null;
            $type = trim((string) ($unit['type'] ?? 'department')) ?: 'department';
            $parent = is_array($unit['parent'] ?? null) ? $unit['parent'] : [];
            $parentCode = trim((string) ($parent['code'] ?? $unit['parent_code'] ?? '')) ?: null;

            if (isset($byCode[$key])) {
                if ($name !== '') {
                    $byCode[$key]['name'] = $name;
                }
                $byCode[$key]['source'] = 'hrm';
                $byCode[$key]['type'] = $type;
                $byCode[$key]['parent_code'] = $parentCode ?? ($byCode[$key]['parent_code'] ?? null);
                $byCode[$key]['hrm_uuid'] = $uuid;

                continue;
            }

            $byCode[$key] = [
                'code' => $code,
                'name' => $name !== '' ? $name : $code,
                'local_department_id' => null,
                'source' => 'hrm',
                'type' => $type,
                'parent_code' => $parentCode,
                'hrm_uuid' => $uuid,
            ];
        }
    }

    /**
     * @param  array<string, array{code:string, name:string, local_department_id:int|null, source:string, type:string, parent_code:string|null, hrm_uuid?:string|null}>  $byCode
     */
    private function mergeEmployeeMeta(array &$byCode): void
    {
        Employee::query()
            ->where('is_active', true)
            ->whereNotNull('meta')
            ->get(['id', 'meta'])
            ->each(function (Employee $employee) use (&$byCode) {
                $meta = is_array($employee->meta) ? $employee->meta : [];
                $name = trim((string) ($meta['department_name'] ?? ''));
                $code = trim((string) ($meta['department_code'] ?? ''));

                if ($code === '' && $name === '') {
                    return;
                }

                if ($code === '') {
                    $code = $this->slugCode($name);
                }

                $key = strtoupper($code);
                if (isset($byCode[$key])) {
                    if ($name !== '' && ($byCode[$key]['name'] === '' || $byCode[$key]['source'] === 'employee')) {
                        $byCode[$key]['name'] = $name;
                    }

                    return;
                }

                $byCode[$key] = [
                    'code' => $code,
                    'name' => $name !== '' ? $name : $code,
                    'local_department_id' => null,
                    'source' => 'employee',
                    'type' => 'department',
                    'parent_code' => null,
                    'hrm_uuid' => null,
                ];
            });
    }

    private function slugCode(string $name): string
    {
        $slug = preg_replace('/\s+/u', '-', mb_strtoupper(trim($name), 'UTF-8')) ?? '';
        $slug = preg_replace('/[^A-Z0-9\-_]/u', '', $slug) ?? '';

        return $slug !== '' ? $slug : 'DEPT-UNKNOWN';
    }
}
