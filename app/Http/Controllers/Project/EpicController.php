<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EpicController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('contribute', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:24'],
        ]);

        $project->epics()->firstOrCreate(
            ['name' => $data['name']],
            ['color' => $data['color'] ?? 'violet'],
        );

        return back()->with('success', 'Đã lưu epic.');
    }
}
