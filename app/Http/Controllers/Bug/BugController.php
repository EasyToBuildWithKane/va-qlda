<?php

namespace App\Http\Controllers\Bug;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bug\StoreBugRequest;
use App\Http\Requests\Bug\UpdateBugRequest;
use App\Http\Resources\BugResource;
use App\Models\Bug;
use App\Support\BugActivityLogger;
use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
use App\Support\Enums\TaskPriority;
use App\Support\NotificationDispatcher;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BugController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Bug::class);

        $account = $request->user();

        $query = Bug::query()
            ->with(['project', 'assignee', 'reporter'])
            ->withCount('comments')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($request->boolean('mine') && $account->employee_id) {
            $query->where('assignee_id', $account->employee_id);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        return Inertia::render('Bug/Index', [
            'bugs' => BugResource::collection($query->paginate(20)->withQueryString()),
            'filters' => (object) $request->only(['status', 'severity', 'priority', 'project_id', 'mine', 'q']),
            'summary' => [
                'open' => Bug::open()->count(),
                'critical' => Bug::open()->whereIn('severity', [BugSeverity::Critical->value, BugSeverity::Blocker->value])->count(),
                'resolved' => Bug::whereIn('status', [BugStatus::Resolved->value, BugStatus::Closed->value])->count(),
            ],
            'options' => $this->options(),
            'can' => ['create' => $account->can('create', Bug::class)],
        ]);
    }

    public function show(Bug $bug): Response
    {
        $this->authorize('view', $bug);

        $bug->load(['project', 'task', 'reporter', 'assignee', 'comments.author']);

        return Inertia::render('Bug/Show', [
            'bug' => (new BugResource($bug))->resolve(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreBugRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] ??= BugStatus::Open->value;

        $bug = Bug::create($data);
        BugActivityLogger::created($bug, $request->user());
        NotificationDispatcher::bugChanged($bug, 'tạo', $request->user());

        return redirect()
            ->route('bugs.show', $bug)
            ->with('success', 'Đã tạo bug.');
    }

    public function update(UpdateBugRequest $request, Bug $bug): RedirectResponse
    {
        $data = $request->validated();

        $oldStatus = $bug->status->value;

        if (array_key_exists('status', $data)) {
            $closed = BugStatus::from($data['status'])->isClosed();
            $data['resolved_at'] = $closed ? ($bug->resolved_at ?? now()) : null;
        }

        $bug->update($data);
        $account = $request->user();

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            BugActivityLogger::statusChanged($bug, $oldStatus, $data['status'], $account);
        }

        $changes = collect($bug->getChanges())->except(['status', 'resolved_at', 'updated_at'])->all();
        if ($changes !== []) {
            BugActivityLogger::updated($bug, $account, $changes);
        }

        NotificationDispatcher::bugChanged($bug->fresh(), 'cập nhật', $account, $changes);

        return back()->with('success', 'Đã cập nhật bug.');
    }

    public function destroy(Bug $bug): RedirectResponse
    {
        $this->authorize('delete', $bug);

        BugActivityLogger::deleted($bug, request()->user());
        $bug->delete();

        return redirect()
            ->route('bugs.index')
            ->with('success', 'Đã xoá bug.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'projects' => Options::projects(),
            'employees' => Options::employees(),
            'severity' => BugSeverity::options(),
            'status' => BugStatus::options(),
            'priority' => TaskPriority::options(),
        ];
    }
}
