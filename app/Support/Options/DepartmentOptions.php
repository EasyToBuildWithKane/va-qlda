<?php

namespace App\Support\Options;

use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DepartmentOptions
{
    /** @return Collection<int, array{id:int, name:string, code:string, color:string}> */
    public function all(): Collection
    {
        return Cache::remember('options.departments', 300, fn () => Department::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color'])
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'color' => $d->color,
            ])
        );
    }

    /** Phòng ban phụ trách mặc định khi tạo dự án (theo mã cấu hình hoặc phòng active đầu tiên). */
    public function defaultOwnerId(): ?int
    {
        $cached = Cache::get('options.departments.default_owner');
        if ($cached !== null && $this->isActiveDepartmentId((int) $cached)) {
            return (int) $cached;
        }

        $id = $this->resolveDefaultOwnerId();
        if ($id !== null) {
            Cache::put('options.departments.default_owner', $id, 3600);
        } else {
            Cache::forget('options.departments.default_owner');
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

    public function flush(): void
    {
        Cache::forget('options.departments');
        Cache::forget('options.departments.default_owner');
    }
}
