<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreSprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\NotificationDispatcher;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\RedirectResponse;

class SprintController extends Controller
{
    public function store(StoreSprintRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        unset($data['sort_order']);

        $sprint = $project->sprints()->create($data);
        $this->syncSprintSortOrders($project);
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
        $this->syncSprintSortOrders($project);
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
        $this->syncSprintSortOrders($project);

        return back()->with('success', 'Đã xoá sprint.');
    }

    /**
     * Gán sort_order 1..n theo ngày bắt đầu (sớm → muộn), không cần người dùng nhập số.
     */
    private function syncSprintSortOrders(Project $project): void
    {
        $sprints = $project->sprints()
            ->orderBy('start_date')
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
