<?php

namespace App\Http\Controllers\Project;

use App\Application\Project\ArchiveProjectUseCase;
use App\Application\Project\CreateProjectUseCase;
use App\Application\Project\DuplicateProjectUseCase;
use App\Application\Project\ProjectIndexQuery;
use App\Application\Project\ProjectMemberRosterMerger;
use App\Application\Project\ProjectShowDataLoader;
use App\Application\Project\UpdateProjectUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\BlockerResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\ProjectAttachmentResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TestCaseResource;
use App\Http\Resources\TestSuiteResource;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\WeeklyReport;
use App\Services\WeeklyReport\WeeklyReportPresenter;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\TaskStatus;
use App\Support\Enums\TestCaseRunResult;
use App\Support\Enums\TestCaseStatus;
use App\Support\NotificationDispatcher;
use App\Support\Options;
use App\Support\ProjectActivityFeedBuilder;
use App\Support\ProjectActivityLogger;
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
        private readonly ProjectIndexQuery $projectIndexQuery,
        private readonly ProjectShowDataLoader $projectShowDataLoader,
        private readonly ProjectMemberRosterMerger $memberRosterMerger,
        private readonly ProjectActivityFeedBuilder $activityFeedBuilder,
        private readonly WeeklyReportPresenter $weeklyReportPresenter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $result = $this->projectIndexQuery->execute($request, $request->user());

        return Inertia::render('Project/Index', [
            'projects' => ProjectListResource::collection($result['projects']),
            'filters' => $result['filters'],
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments()->values()->all(),
            'orgTeamOptions' => $result['orgTeamOptions'],
            'employees' => Options::employees(),
            'summary' => $result['summary'],
            'can' => $result['can'],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Project/Create', [
            'employees' => Options::employees()->values()->all(),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments()->values()->all(),
            'suggestedCode' => $this->createProject->suggestCode(),
            'defaultDepartmentId' => Options::defaultOwnerDepartmentId(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->createProject->execute($request->validated());
        ProjectActivityLogger::created($project, $request->user());
        NotificationDispatcher::projectCreated($project, $request->user());

        $route = $request->input('after') === 'continue' ? 'projects.edit' : 'projects.show';

        return redirect()
            ->route($route, $project)
            ->with('success', 'Đã tạo dự án.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project = $this->projectShowDataLoader->load($project);
        $this->memberRosterMerger->mergeForProject($project);

        $feedbackQuery = Feedback::query()->where('project_id', $project->id);
        $openBlockerCount = $project->blockers
            ->filter(fn ($b) => ! in_array($b->status->value, ['resolved', 'closed'], true))
            ->count();

        return Inertia::render('Project/Show', [
            'project' => (new ProjectResource($project))->resolve(),
            'summary' => [
                'progress' => $project->progress(),
                'members' => $project->members->count(),
                'tasks_total' => $project->tasks->count(),
                'tasks_done' => $project->tasks->where('status', TaskStatus::Done)->count(),
                'sprints_total' => $project->sprints->count(),
                'sprints_active' => $project->sprints->where('status', SprintStatus::Active)->count(),
                'blockers_open' => $openBlockerCount,
                'blockers_total' => $project->blockers->count(),
            ],
            'attachments' => ProjectAttachmentResource::collection($project->attachments)->resolve(),
            'sprints' => SprintResource::collection($project->sprints)->resolve(),
            'epics' => \App\Http\Resources\EpicResource::collection($project->epics)->resolve(),
            'tasks' => TaskResource::collection($project->tasks)->resolve(),
            'blockers' => BlockerResource::collection($project->blockers)->resolve(),
            'feedbacks' => FeedbackResource::collection($project->feedbacks)->resolve(),
            'testCases' => TestCaseResource::collection($project->testCases)->resolve(),
            'testSuites' => TestSuiteResource::collection($project->testSuites)->resolve(),
            'testCaseSummary' => [
                'total' => $project->testCases->count(),
                'ready' => $project->testCases->filter(fn ($tc) => $tc->status === TestCaseStatus::Ready)->count(),
                'pass' => $project->testCases->filter(fn ($tc) => $tc->last_result === TestCaseRunResult::Pass->value)->count(),
                'fail' => $project->testCases->filter(fn ($tc) => $tc->last_result === TestCaseRunResult::Fail->value)->count(),
                'not_run' => $project->testCases->filter(fn ($tc) => $tc->isNotRun())->count(),
            ],
            'feedbackSummary' => [
                'open' => (clone $feedbackQuery)->open()->count(),
                'resolved' => (clone $feedbackQuery)->where('status', FeedbackStatus::Resolved->value)->count(),
                'avg_rating' => round((float) (clone $feedbackQuery)->whereNotNull('rating')->avg('rating'), 1) ?: null,
            ],
            'can' => [
                'feedbackCreate' => $request->user()->can('create', Feedback::class),
                'weeklyGenerate' => $request->user()->can('generate', [WeeklyReport::class, $project]),
            ],
            'options' => [
                'employees' => Options::employees(),
                'enums' => Options::enums(),
            ],
            'activityFeed' => $this->activityFeedBuilder->forProject($project),
            'weeklyReports' => $this->weeklyReportPresenter->overview($project),
            'weeklyReport' => $this->weeklyReportPresenter->detail($project, $request),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Project/Edit', [
            'project' => (new ProjectResource($project->load(['manager', 'department'])))->resolve(),
            'employees' => Options::employees()->values()->all(),
            'statusOptions' => ProjectStatus::options(),
            'typeOptions' => ProjectType::options(),
            'scopeOptions' => ProjectScope::options(),
            'regionOptions' => Options::regions(),
            'departmentOptions' => Options::departments()->values()->all(),
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
        ProjectActivityLogger::duplicated($copy, $project, request()->user());

        return redirect()
            ->route('projects.edit', $copy)
            ->with('success', 'Đã nhân bản dự án.');
    }

    public function archive(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->archiveProject->execute($project);
        $archived = $project->fresh();
        ProjectActivityLogger::archived($archived, $request->user());
        NotificationDispatcher::projectArchived($archived, $request->user());

        return back()->with('success', 'Đã lưu trữ dự án.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        ProjectActivityLogger::deleted($project, $request->user());
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Đã xoá dự án.');
    }
}
