<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ReorderSprintsRequest;
use App\Http\Requests\Project\StoreSprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\NotificationDispatcher;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SprintController extends Controller
{
    public function store(StoreSprintRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        unset($data['sort_order']);

        $maxOrder = (int) $project->sprints()->max('sort_order');
        $data['sort_order'] = $maxOrder + 1;

        $sprint = $project->sprints()->create($data);
        ProjectActivityLogger::sprintCreated($project, $sprint, $request->user());
        NotificationDispatcher::sprintChanged($project, $sprint, 'tạo', $request->user());

        return back()->with('success', 'Đã thêm sprint.');
    }

    public function update(StoreSprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        abort_unless($sprint->project_id === $project->id, 404);

        $data = $request->validated();
        unset($data['sort_order']);

        $sprint->update($data);
        ProjectActivityLogger::sprintUpdated($project, $sprint->fresh(), $request->user());
        NotificationDispatcher::sprintChanged($project, $sprint, 'cập nhật', $request->user());

        return back()->with('success', 'Đã cập nhật sprint.');
    }

    public function destroy(Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($sprint->project_id === $project->id, 404);

        $name = $sprint->name;
        $sprintId = $sprint->id;
        ProjectActivityLogger::sprintDeleted($project, $name, $sprintId, request()->user());
        NotificationDispatcher::sprintChanged($project, $sprint, 'xoá', request()->user());
        $sprint->delete();
        $this->renumberSprintSortOrders($project);

        return back()->with('success', 'Đã xoá sprint.');
    }

    public function reorder(ReorderSprintsRequest $request, Project $project): RedirectResponse
    {
        $ids = $request->validated('ids');

        DB::transaction(function () use ($project, $ids) {
            foreach ($ids as $index => $id) {
                Sprint::query()
                    ->where('project_id', $project->id)
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return back()->with('success', 'Đã cập nhật thứ tự sprint.');
    }

    /** Giữ thứ tự hiện tại, đánh lại 1..n sau khi xoá. */
    private function renumberSprintSortOrders(Project $project): void
    {
        $sprints = $project->sprints()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($sprints as $index => $sprint) {
            $order = $index + 1;
            if ((int) $sprint->sort_order !== $order) {
                $sprint->updateQuietly(['sort_order' => $order]);
            }
        }
    }
}
