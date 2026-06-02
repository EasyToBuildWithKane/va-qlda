<?php

namespace App\Http\Controllers\Project;

use App\Application\Project\ArchiveProjectUseCase;
use App\Application\Project\CreateProjectUseCase;
use App\Application\Project\DuplicateProjectUseCase;
use App\Application\Project\UpdateProjectUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\BlockerResource;
use App\Http\Resources\ProjectAttachmentResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
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
    public function __construct(
        private readonly CreateProjectUseCase $createProject,
        private readonly UpdateProjectUseCase $updateProject,
        private readonly DuplicateProjectUseCase $duplicateProject,
        private readonly ArchiveProjectUseCase $archiveProject,
    ) {}

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
            ],
            'can' => ['create' => $account->can('create', Project::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Project/Create', [
            'employees' => Options::employees(),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments(),
            'suggestedCode' => $this->createProject->suggestCode(),
            'defaultDepartmentId' => Options::defaultOwnerDepartmentId(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->createProject->execute($request->validated());

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
            'epics',
            'tasks' => fn ($q) => $q->with([
                'assignee',
                'assignees',
                'reporter',
                'reviewer',
                'epic',
                'parent:id,title',
                'sprint:id,project_id,name',
                'dependencies:id,title,status',
                'dependents:id,title,status,progress',
                'subtasks' => fn ($s) => $s->with('assignee'),
                'watchers',
                'attachments' => fn ($a) => $a->with('uploadedBy'),
                'activities' => fn ($a) => $a->with('employee')->limit(100),
                'worklogs' => fn ($w) => $w->with('employee')->latest('date'),
                'comments' => fn ($c) => $c->whereNull('parent_id')->with(['author', 'replies.author'])->latest(),
            ])->orderBy('order_column'),
            'blockers' => fn ($q) => $q->with([
                'raisedBy',
                'owner',
                'comments' => fn ($c) => $c->with('author')->latest(),
                'attachments' => fn ($a) => $a->with('uploadedBy')->latest(),
                'activities' => fn ($a) => $a->with('employee')->latest(),
            ])->latest(),
            'attachments' => fn ($q) => $q->with([
                'uploadedBy',
                'updatedBy',
                'activities' => fn ($a) => $a->with('employee')->latest(),
            ])->latest(),
        ]);

        return Inertia::render('Project/Show', [
            'project' => (new ProjectResource($project))->resolve(),
            'attachments' => ProjectAttachmentResource::collection($project->attachments)->resolve(),
            'sprints' => SprintResource::collection($project->sprints)->resolve(),
            'epics' => \App\Http\Resources\EpicResource::collection($project->epics)->resolve(),
            'tasks' => TaskResource::collection($project->tasks)->resolve(),
            'blockers' => BlockerResource::collection($project->blockers)->resolve(),
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
        $this->updateProject->execute($project, $request->validated(), $request->user());

        $route = $request->input('after') === 'continue' ? 'projects.edit' : 'projects.show';

        return redirect()
            ->route($route, $project)
            ->with('success', 'Đã cập nhật dự án.');
    }

    public function updateType(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'type' => ['required', Rule::in(ProjectType::values())],
        ]);

        $project->update($data);

        return back();
    }

    public function updateDepartment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $project->update(['department_id' => $data['department_id'] ?? null]);

        return back();
    }

    public function duplicate(Project $project): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $copy = $this->duplicateProject->execute($project);

        return redirect()
            ->route('projects.edit', $copy)
            ->with('success', 'Đã nhân bản dự án.');
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->archiveProject->execute($project);

        return back()->with('success', 'Đã lưu trữ dự án.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Đã xoá dự án.');
    }
}
