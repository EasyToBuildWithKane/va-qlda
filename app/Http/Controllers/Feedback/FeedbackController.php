<?php

namespace App\Http\Controllers\Feedback;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Http\Requests\Feedback\UpdateFeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use App\Support\Enums\FeedbackCategory;
use App\Support\Enums\FeedbackStatus;
use App\Support\Enums\TaskPriority;
use App\Support\FeedbackActivityLogger;
use App\Support\NotificationDispatcher;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Feedback::class);

        $account = $request->user();

        $query = Feedback::query()
            ->with(['project', 'assignee', 'reporter'])
            ->withCount('comments')
            ->latest();

        if ($scope = $request->query('scope')) {
            if ($scope === 'open') {
                $query->open();
            }
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($request->boolean('mine') && $account->employee_id) {
            $query->where('assignee_id', $account->employee_id);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($assigneeId = $request->query('assignee_id')) {
            $query->where('assignee_id', $assigneeId);
        }
        if ($request->query('rating') !== null && $request->query('rating') !== '') {
            $query->where('rating', (int) $request->query('rating'));
        }
        if ($from = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 50);

        return Inertia::render('Feedback/Index', [
            'feedback' => FeedbackResource::collection($query->paginate($perPage)->withQueryString()),
            'filters' => (object) $request->only([
                'status', 'category', 'project_id', 'mine', 'q', 'scope', 'per_page',
                'priority', 'assignee_id', 'rating', 'created_from', 'created_to',
            ]),
            'summary' => [
                'total' => Feedback::count(),
                'open' => Feedback::open()->count(),
                'new' => Feedback::where('status', FeedbackStatus::New->value)->count(),
                'resolved' => Feedback::where('status', FeedbackStatus::Resolved->value)->count(),
                'avg_rating' => round((float) Feedback::whereNotNull('rating')->avg('rating'), 1),
            ],
            'options' => $this->options(),
            'can' => ['create' => $account->can('create', Feedback::class)],
        ]);
    }

    public function show(Feedback $feedback): Response
    {
        $this->authorize('view', $feedback);

        $feedback->load(['project', 'reporter', 'assignee', 'comments.author']);

        return Inertia::render('Feedback/Show', [
            'feedback' => (new FeedbackResource($feedback))->resolve(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreFeedbackRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['return_to']);
        $data['status'] ??= FeedbackStatus::New->value;

        $feedback = Feedback::create($data);
        FeedbackActivityLogger::created($feedback, $request->user());
        NotificationDispatcher::feedbackChanged($feedback, 'tạo', $request->user());

        if ($request->input('return_to') === 'project' && $feedback->project_id) {
            return redirect()
                ->route('projects.show', ['project' => $feedback->project_id, 'tab' => 'feedback'])
                ->with('success', 'Đã ghi nhận phản hồi.');
        }

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', 'Đã ghi nhận phản hồi.');
    }

    public function update(UpdateFeedbackRequest $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validated();

        $oldStatus = $feedback->status->value;

        if (array_key_exists('status', $data)) {
            $closed = FeedbackStatus::from($data['status'])->isClosed();
            $data['resolved_at'] = $closed ? ($feedback->resolved_at ?? now()) : null;
        }

        $feedback->update($data);
        $account = $request->user();

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            FeedbackActivityLogger::statusChanged($feedback, $oldStatus, $data['status'], $account);
        }

        $changes = collect($feedback->getChanges())->except(['status', 'resolved_at', 'updated_at'])->all();
        if ($changes !== []) {
            FeedbackActivityLogger::updated($feedback, $account, $changes);
        }

        NotificationDispatcher::feedbackChanged($feedback->fresh(), 'cập nhật', $account, $changes);

        return back()->with('success', 'Đã cập nhật phản hồi.');
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $this->authorize('delete', $feedback);

        FeedbackActivityLogger::deleted($feedback, request()->user());
        $feedback->delete();

        return redirect()
            ->route('feedback.index')
            ->with('success', 'Đã xoá phản hồi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'projects' => Options::projects(),
            'employees' => Options::employees(),
            'category' => FeedbackCategory::options(),
            'status' => FeedbackStatus::options(),
            'priority' => TaskPriority::options(),
        ];
    }
}
