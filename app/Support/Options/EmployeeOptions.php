<?php

namespace App\Support\Options;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EmployeeOptions
{
    /** @return Collection<int, array{id:int, name:string, avatar_path:string|null}> */
    public function all(): Collection
    {
        return Cache::remember('options.employees', 300, fn () => Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'avatar_path' => $e->avatar_path,
            ])
        );
    }

    public function flush(): void
    {
        Cache::forget('options.employees');
    }
}
