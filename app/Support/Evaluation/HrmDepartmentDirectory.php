<?php

namespace App\Support\Evaluation;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

/**
 * Danh mục phòng ban cho cấu hình đánh giá.
 * HRM chưa có /departments — lấy distinct từ employees.meta + merge local departments.
 */
final class HrmDepartmentDirectory
{
    public const CACHE_KEY = 'evaluation.department_directory.v1';

    public const CACHE_TTL_SECONDS = 86400;

    /**
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string}>
     */
    public function all(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        /** @var list<array{code:string, name:string, local_department_id:int|null, source:string}> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{code:string, name:string, local_department_id:int|null, source:string}|null
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
     * @return list<array{code:string, name:string, local_department_id:int|null, source:string}>
     */
    private function build(): array
    {
        /** @var array<string, array{code:string, name:string, local_department_id:int|null, source:string}> $byCode */
        $byCode = [];

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
                ];
            });

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
                    if ($name !== '' && ($byCode[$key]['name'] === '' || $byCode[$key]['source'] === 'hrm')) {
                        $byCode[$key]['name'] = $name;
                    }
                    if ($byCode[$key]['source'] === 'local') {
                        return;
                    }
                    $byCode[$key]['source'] = 'hrm';

                    return;
                }

                $byCode[$key] = [
                    'code' => $code,
                    'name' => $name !== '' ? $name : $code,
                    'local_department_id' => null,
                    'source' => 'hrm',
                ];
            });

        $list = array_values($byCode);
        usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    private function slugCode(string $name): string
    {
        $slug = preg_replace('/\s+/u', '-', mb_strtoupper(trim($name), 'UTF-8')) ?? '';
        $slug = preg_replace('/[^A-Z0-9\-_]/u', '', $slug) ?? '';

        return $slug !== '' ? $slug : 'DEPT-UNKNOWN';
    }
}
