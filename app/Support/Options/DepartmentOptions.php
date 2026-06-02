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
        return Cache::remember('options.departments', 300, fn () =>
            Department::active()
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

    /** Phòng ban phụ trách mặc định khi tạo dự án (Phòng Công nghệ). */
    public function defaultOwnerId(): ?int
    {
        return Cache::remember('options.departments.default_owner', 3600, function () {
            $code = config('project.default_owner_department_code');

            if ($code) {
                $byCode = Department::query()->where('code', $code)->value('id');
                if ($byCode) {
                    return $byCode;
                }
            }

            return Department::query()
                ->where('name', 'like', '%Công nghệ%')
                ->orderBy('sort_order')
                ->value('id');
        });
    }

    public function flush(): void
    {
        Cache::forget('options.departments');
        Cache::forget('options.departments.default_owner');
    }
}
