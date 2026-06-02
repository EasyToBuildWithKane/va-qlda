<?php

namespace App\Support\Options;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProjectOptions
{
    /** @return Collection<int, array{id:int, name:string, code:string, color:string}> */
    public function all(): Collection
    {
        return Cache::remember('options.projects', 300, fn () =>
            Project::active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'color'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'color' => $p->color,
                ])
        );
    }

    public function flush(): void
    {
        Cache::forget('options.projects');
    }
}
