<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreSprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;

class SprintController extends Controller
{
    public function store(StoreSprintRequest $request, Project $project): RedirectResponse
    {
        $project->sprints()->create($request->validated());

        return back()->with('success', 'Đã thêm sprint.');
    }

    public function update(StoreSprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        abort_unless($sprint->project_id === $project->id, 404);

        $sprint->update($request->validated());

        return back()->with('success', 'Đã cập nhật sprint.');
    }

    public function destroy(Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($sprint->project_id === $project->id, 404);

        $sprint->delete();

        return back()->with('success', 'Đã xoá sprint.');
    }
}
