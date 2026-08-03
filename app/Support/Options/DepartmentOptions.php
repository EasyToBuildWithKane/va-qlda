<?php

namespace App\Support\Options;

use App\Models\Department;
use App\Support\Evaluation\HrmDepartmentDirectory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DepartmentOptions
{
    public function __construct(
        private readonly HrmDepartmentDirectory $hrmDepartments,
    ) {}

    /** @return Collection<int, array{id:int, name:string, code:string, color:string}> */
    public function all(): Collection
    {
        return Cache::remember('options.departments.v2', 300, function () {
            $this->mirrorHrmDepartments();

            return Department::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'color'])
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'code' => $d->code,
                    'color' => $d->color,
                ])
                ->values();
        });
    }

    /** Phòng ban phụ trách mặc định khi tạo dự án (theo mã cấu hình hoặc phòng active đầu tiên). */
    public function defaultOwnerId(): ?int
    {
        $cached = Cache::get('options.departments.default_owner.v2');
        if ($cached !== null && $this->isActiveDepartmentId((int) $cached)) {
            return (int) $cached;
        }

        // Bảo đảm đã mirror HRM trước khi chọn mặc định (tránh cache id rỗng khi local trống).
        $this->all();

        $id = $this->resolveDefaultOwnerId();
        if ($id !== null) {
            Cache::put('options.departments.default_owner.v2', $id, 3600);
        } else {
            Cache::forget('options.departments.default_owner.v2');
        }

        return $id;
    }

    public function isActiveDepartmentId(int $id): bool
    {
        return Department::active()->whereKey($id)->exists();
    }

    private function resolveDefaultOwnerId(): ?int
    {
        $code = config('project.default_owner_department_code');

        if ($code) {
            $byCode = Department::active()->where('code', $code)->value('id');
            if ($byCode) {
                return (int) $byCode;
            }
        }

        // HRM dùng mã PCN cho Phòng Công nghệ — fallback khi cấu hình còn mã seeder cũ.
        $byHrmCode = Department::active()->where('code', 'PCN')->value('id');
        if ($byHrmCode) {
            return (int) $byHrmCode;
        }

        $byName = Department::active()
            ->where('name', 'like', '%Công nghệ%')
            ->orderBy('sort_order')
            ->value('id');
        if ($byName) {
            return (int) $byName;
        }

        $fallback = Department::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }

    /**
     * Đồng bộ org-unit HRM vào bảng local `departments` (FK dự án vẫn dùng id local).
     * Index UI phòng ban đã gỡ — danh mục lấy từ HRM.
     */
    private function mirrorHrmDepartments(): void
    {
        $created = false;

        foreach ($this->hrmDepartments->all() as $row) {
            if (($row['source'] ?? '') !== 'hrm') {
                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '') {
                continue;
            }

            $dept = Department::query()->firstOrNew(['code' => $code]);

            if (! $dept->exists) {
                $dept->fill([
                    'name' => $name !== '' ? $name : $code,
                    'color' => 'brand',
                    'sort_order' => 0,
                    'is_active' => true,
                ]);
                $dept->save();
                $created = true;

                continue;
            }

            if ($name !== '' && $dept->name !== $name) {
                $dept->update(['name' => $name]);
            }
        }

        if ($created) {
            $this->hrmDepartments->forget();
        }
    }

    public function flush(): void
    {
        Cache::forget('options.departments');
        Cache::forget('options.departments.v2');
        Cache::forget('options.departments.default_owner');
        Cache::forget('options.departments.default_owner.v2');
    }
}
