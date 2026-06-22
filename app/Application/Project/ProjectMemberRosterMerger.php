<?php

namespace App\Application\Project;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Thẻ dự án & tổng quan chi tiết: thành viên pivot + người được gán task (và PM nếu chưa có trong danh sách).
 */
class ProjectMemberRosterMerger
{
    public function mergeForProject(Project $project, int $maxMembers = 50): void
    {
        $this->mergeForCollection(collect([$project]), $maxMembers);
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    public function mergeForCollection(Collection $projects, int $maxMembers = 8): void
    {
        if ($projects->isEmpty()) {
            return;
        }

        $projectIds = $projects->pluck('id')->all();
        $grammar = DB::connection()->getQueryGrammar();

        $assigneeRows = DB::table('tasks')
            ->leftJoin('task_assignees', 'tasks.id', '=', 'task_assignees.task_id')
            ->whereIn('tasks.project_id', $projectIds)
            ->whereNull('tasks.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('tasks.assignee_id')
                    ->orWhereNotNull('task_assignees.employee_id');
            })
            ->selectRaw(
                $grammar->wrap('tasks.project_id').', COALESCE('
                .$grammar->wrap('task_assignees.employee_id').', '
                .$grammar->wrap('tasks.assignee_id').') as employee_id'
            )
            ->distinct()
            ->get()
            ->groupBy('project_id');

        $extraIds = $assigneeRows->flatten(1)->pluck('employee_id')->filter()->unique()->values();
        $employees = $extraIds->isEmpty()
            ? collect()
            : Employee::query()->whereIn('id', $extraIds)->get()->keyBy('id');

        foreach ($projects as $project) {
            $seen = $project->members->pluck('id')->flip();
            $merged = $project->members;

            if ($project->manager_id && $project->relationLoaded('manager') && $project->manager) {
                $mid = (int) $project->manager_id;
                if (! $seen->has($mid)) {
                    $merged->prepend($project->manager);
                    $seen->put($mid, 1);
                }
            }

            foreach ($assigneeRows->get($project->id, collect()) as $row) {
                $eid = (int) $row->employee_id;
                if ($seen->has($eid) || ! $employees->has($eid)) {
                    continue;
                }
                $merged->push($employees->get($eid));
                $seen->put($eid, 1);
                if ($merged->count() >= $maxMembers) {
                    break;
                }
            }

            $project->setRelation('members', $merged);
        }
    }
}
