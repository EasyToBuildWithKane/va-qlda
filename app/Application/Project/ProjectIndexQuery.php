<?php

namespace App\Application\Project;

use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectIndexQuery
{
    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [5, 10, 15, 20, 50, 100];

    public const DEFAULT_PER_PAGE = 100;

    public function __construct(
        private readonly ProjectSummaryQuery $summaryQuery,
    ) {}

    /**
     * @return array{
     *     projects: LengthAwarePaginator,
     *     filters: \stdClass,
     *     summary: array{total: int, active: int, completed: int, overdue: int},
     *     can: array{create: bool}
     * }
     */
    public function execute(Request $request, SystemAccount $account): array
    {
        $query = Project::query()
            ->with([
                'manager',
                'department',
                'members' => fn ($q) => $q->wherePivot('is_active', true)->orderByDesc('project_member.joined_at'),
            ])
            ->withCount(['members', 'tasks'])
            ->withCount(['blockers as open_blocker_count' => fn ($q) => $q->open()])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($scope = $request->query('scope')) {
            $query->where('scope', $scope);
        }
        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($request->boolean('mine') && $account->employee_id) {
            $eid = $account->employee_id;
            $query->where(fn ($q) => $q
                ->where('manager_id', $eid)
                ->orWhereHas('members', fn ($m) => $m->where('employee_id', $eid)));
        }

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $projects = $query->paginate($perPage)->withQueryString();
        $this->mergeTaskAssigneesIntoMembers($projects->getCollection());

        return [
            'projects' => $projects,
            'filters' => (object) $request->only([
                'status', 'type', 'scope', 'department_id', 'mine', 'q', 'per_page',
            ]),
            'summary' => $this->summaryQuery->execute(),
            'can' => ['create' => $account->can('create', Project::class)],
        ];
    }

    /**
     * Thẻ dự án: thành viên dự án + người được gán task/sprint (tối đa 8 avatar).
     *
     * @param  Collection<int, Project>  $projects
     */
    private function mergeTaskAssigneesIntoMembers(Collection $projects): void
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

            foreach ($assigneeRows->get($project->id, collect()) as $row) {
                $eid = (int) $row->employee_id;
                if ($seen->has($eid) || ! $employees->has($eid)) {
                    continue;
                }
                $merged->push($employees->get($eid));
                $seen->put($eid, 1);
                if ($merged->count() >= 8) {
                    break;
                }
            }

            $project->setRelation('members', $merged);
        }
    }
}
