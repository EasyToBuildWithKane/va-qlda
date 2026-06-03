<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Models\SystemAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ProjectIndexQuery
{
    public function __construct(
        private readonly ProjectSummaryQuery $summaryQuery,
    ) {}

    /**
     * @return array{
     *     projects: LengthAwarePaginator<int, Project>,
     *     filters: object,
     *     summary: array{total: int, active: int, completed: int, overdue: int},
     *     can: array{create: bool}
     * }
     */
    public function execute(Request $request, SystemAccount $account): array
    {
        $query = Project::query()
            ->with(['manager', 'department'])
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

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 15, 20], true)) {
            $perPage = 10;
        }

        return [
            'projects' => $query->paginate($perPage)->withQueryString(),
            'filters' => (object) $request->only([
                'status', 'type', 'scope', 'department_id', 'mine', 'q', 'per_page',
            ]),
            'summary' => $this->summaryQuery->execute(),
            'can' => ['create' => $account->can('create', Project::class)],
        ];
    }
}
