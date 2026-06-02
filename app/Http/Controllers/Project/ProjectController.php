<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\BlockerResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\WorklogResource;
use App\Models\Project;
use App\Models\Worklog;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Options;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $account = $request->user();

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

        $projects = $query->get();

        return Inertia::render('Project/Index', [
            'projects' => ProjectListResource::collection($projects),
            'filters' => (object) $request->only(['status', 'type', 'scope', 'department_id', 'mine', 'q']),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments(),
            'employees' => Options::employees(),
            'summary' => [
                'total' => Project::count(),
                'active' => Project::where('status', ProjectStatus::Active->value)->count(),
                'completed' => Project::where('status', ProjectStatus::Completed->value)->count(),
                'overdue' => Project::whereNotNull('due_date')
                    ->whereDate('due_date', '<', Carbon::today())
                    ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
                    ->count(),
                'budget' => (float) Project::sum('budget'),
                'spentBudget' => (float) Project::sum('actual_budget'),
            ],
            'can' => ['create' => $account->can('create', Project::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Project/Create', [
            'employees' => Options::employees(),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments(),
            'suggestedCode' => $this->suggestCode(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        // "Lưu & tiếp tục" keeps the user on the edit screen of the new project.
        $route = $request->input('after') === 'continue' ? 'projects.edit' : 'projects.show';

        return redirect()
            ->route($route, $project)
            ->with('success', 'Đã tạo dự án.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'manager',
            'department',
            'members',
            'sprints' => fn ($q) => $q->withCount('tasks'),
            'tasks' => fn ($q) => $q->with(['assignee', 'dependencies:id', 'worklogs:id,task_id,hours,cost'])
                ->orderBy('order_column'),
            'blockers' => fn ($q) => $q->with(['raisedBy', 'owner'])->latest(),
        ]);

        $worklogs = Worklog::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
            ->with(['task:id,title', 'employee'])
            ->latest('date')
            ->limit(100)
            ->get();

        return Inertia::render('Project/Show', [
            'project' => (new ProjectResource($project))->resolve(),
            'sprints' => SprintResource::collection($project->sprints)->resolve(),
            'tasks' => TaskResource::collection($project->tasks)->resolve(),
            'blockers' => BlockerResource::collection($project->blockers)->resolve(),
            'worklogs' => WorklogResource::collection($worklogs)->resolve(),
            'cost' => $this->costSummary($project),
            'options' => [
                'employees' => Options::employees(),
                'enums' => Options::enums(),
            ],
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Project/Edit', [
            'project' => (new ProjectResource($project->load(['manager', 'department'])))->resolve(),
            'employees' => Options::employees(),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        // "Lưu & tiếp tục" stays on the edit screen; otherwise go to the detail page.
        $route = $request->input('after') === 'continue' ? 'projects.edit' : 'projects.show';

        return redirect()
            ->route($route, $project)
            ->with('success', 'Đã cập nhật dự án.');
    }

    /**
     * Lightweight type change used by the portfolio Kanban (drag between columns).
     */
    public function updateType(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'type' => ['required', Rule::in(ProjectType::values())],
        ]);

        $project->update($data);

        return back();
    }

    /**
     * Lightweight department change used by the portfolio Kanban (grouped by department).
     */
    public function updateDepartment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $project->update(['department_id' => $data['department_id'] ?? null]);

        return back();
    }

    /**
     * Nhân bản dự án (quick action trên DataGrid) → mở trang sửa bản sao.
     */
    public function duplicate(Project $project): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $copy = $project->replicate(['code']);
        $copy->code = $this->suggestCode();
        $copy->name = $project->name.' (bản sao)';
        $copy->status = ProjectStatus::Planning->value;
        $copy->save();

        return redirect()
            ->route('projects.edit', $copy)
            ->with('success', 'Đã nhân bản dự án.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Đã xoá dự án.');
    }

    /**
     * Budget vs. actual labour cost, plus a per-member breakdown for the chart.
     *
     * @return array<string, mixed>
     */
    private function costSummary(Project $project): array
    {
        $logs = Worklog::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
            ->with('employee:id,full_name')
            ->get(['id', 'employee_id', 'hours', 'cost']);

        $byMember = $logs->groupBy('employee_id')->map(fn ($group) => [
            'name' => $group->first()->employee?->full_name ?? '—',
            'hours' => (float) $group->sum('hours'),
            'cost' => (float) $group->sum('cost'),
        ])->values();

        $labor = (float) $logs->sum('cost');
        $budget = $project->budget !== null ? (float) $project->budget : null;

        return [
            'budget' => $budget,
            'labor_cost' => $labor,
            'remaining' => $budget !== null ? $budget - $labor : null,
            'utilization' => $budget ? (int) round($labor / $budget * 100) : null,
            'total_hours' => (float) $logs->sum('hours'),
            'by_member' => $byMember,
        ];
    }

    private function suggestCode(): string
    {
        $last = Project::orderByDesc('id')->value('id') ?? 0;

        return 'PRJ-'.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
