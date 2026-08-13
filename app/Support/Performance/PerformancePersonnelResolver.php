<?php

namespace App\Support\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\DashboardPersonnelScope;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\Evaluation\HrmEmployeeDirectory;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Support\Collection;

/**
 * Phạm vi nhân sự Performance/Audit theo phòng ban HRM (meta + danh mục org-unit),
 * fallback Phòng Công nghệ khi user chưa gắn đơn vị.
 */
class PerformancePersonnelResolver
{
    public const ALL = 'all';

    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
        private readonly HrmEmployeeDirectory $employees,
        private readonly WorkspaceScopeResolver $workspace,
        private readonly DashboardPersonnelScope $fallbackScope,
    ) {}

    /**
     * Đồng bộ nhân sự từ HRM (cache 1h) trước khi lọc roster.
     */
    public function syncEmployees(): void
    {
        $this->employees->options();
    }

    /**
     * @return array{code: string|null, name: string|null, local_id: int|null, all: bool}
     */
    public function resolveDepartment(?string $query, ?SystemAccount $account): array
    {
        $raw = trim((string) $query);

        if (strcasecmp($raw, self::ALL) === 0) {
            return ['code' => null, 'name' => null, 'local_id' => null, 'all' => true];
        }

        if ($raw !== '') {
            return $this->hydrateDepartment($raw);
        }

        $own = $account ? $this->workspace->ownDepartmentCode($account) : null;
        if (filled($own)) {
            return $this->hydrateDepartment((string) $own);
        }

        $fallback = $this->fallbackScope->department();
        if ($fallback) {
            return [
                'code' => $fallback->code,
                'name' => $fallback->name,
                'local_id' => $fallback->id,
                'all' => false,
            ];
        }

        return ['code' => null, 'name' => null, 'local_id' => null, 'all' => true];
    }

    /**
     * @return Collection<int, int>
     */
    public function employeeIds(?string $departmentCode, bool $allDepartments, ?string $unitKey = null): Collection
    {
        if ($allDepartments && ! filled($departmentCode)) {
            $ids = Employee::query()->where('is_active', true)->orderBy('full_name')->pluck('id');

            return $this->filterByUnit($ids, $unitKey);
        }

        $dept = filled($departmentCode) ? $this->departments->findByCode($departmentCode) : null;
        $name = $dept['name'] ?? null;
        $localId = $dept['local_department_id'] ?? null;
        $unitCodes = collect($this->departments->units($departmentCode))
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        $codes = collect([$departmentCode, ...$unitCodes])
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fromMeta = collect();
        if ($codes !== [] || filled($name)) {
            $fromMeta = Employee::query()
                ->where('is_active', true)
                ->where(function ($q) use ($codes, $name) {
                    if ($codes !== []) {
                        $q->whereIn('meta->department_code', $codes)
                            ->orWhereIn('meta->unit_code', $codes);
                    }
                    if (filled($name)) {
                        if ($codes !== []) {
                            $q->orWhere('meta->department_name', $name);
                        } else {
                            $q->where('meta->department_name', $name);
                        }
                    }
                })
                ->pluck('id');
        }

        $fromPivot = collect();
        if ($localId) {
            $fromPivot = Department::query()->whereKey($localId)->first()
                ?->members()->pluck('employees.id') ?? collect();
        }

        $fromFallback = collect();
        if ($fromMeta->isEmpty() && $fromPivot->isEmpty() && $localId && $localId === $this->fallbackScope->departmentId()) {
            $fromFallback = $this->fallbackScope->employeeIds();
        }

        $ids = $fromMeta
            ->merge($fromPivot)
            ->merge($fromFallback)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $this->filterByUnit($ids, $unitKey);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function departmentOptions(): array
    {
        return collect($this->departments->departments())
            ->map(fn (array $d) => [
                'value' => $d['code'],
                'label' => $d['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function unitOptions(?string $departmentCode): array
    {
        return collect($this->departments->units($departmentCode))
            ->map(fn (array $u) => [
                'value' => $u['code'],
                'label' => $u['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{code: string|null, name: string|null, local_id: int|null, all: bool}
     */
    private function hydrateDepartment(string $raw): array
    {
        if (is_numeric($raw)) {
            $local = Department::query()->find((int) $raw);
            if ($local) {
                return [
                    'code' => $local->code ?: (string) $local->id,
                    'name' => $local->name,
                    'local_id' => $local->id,
                    'all' => false,
                ];
            }
        }

        $row = $this->departments->findByCode($raw);
        if ($row) {
            $code = $row['code'];
            if (($row['type'] ?? '') === 'unit' && filled($row['parent_code'] ?? null)) {
                $parent = $this->departments->findByCode((string) $row['parent_code']);
                if ($parent) {
                    return [
                        'code' => $parent['code'],
                        'name' => $parent['name'],
                        'local_id' => $parent['local_department_id'],
                        'all' => false,
                    ];
                }
            }

            return [
                'code' => $code,
                'name' => $row['name'],
                'local_id' => $row['local_department_id'],
                'all' => false,
            ];
        }

        return [
            'code' => $raw,
            'name' => $raw,
            'local_id' => null,
            'all' => false,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int, int>
     */
    private function filterByUnit(Collection $ids, ?string $unitKey): Collection
    {
        $key = trim((string) $unitKey);
        if ($key === '' || $ids->isEmpty()) {
            return $ids->map(fn ($id) => (int) $id)->values();
        }

        if (is_numeric($key)) {
            return $ids->map(fn ($id) => (int) $id)->values();
        }

        $matched = Employee::query()
            ->whereIn('id', $ids)
            ->where(function ($q) use ($key) {
                $q->where('meta->unit_code', $key)
                    ->orWhere('meta->unit_name', $key);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return $matched;
    }
}
