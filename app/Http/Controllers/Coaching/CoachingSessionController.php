<?php

namespace App\Http\Controllers\Coaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoachingSessionResource;
use App\Models\CoachingAssignment;
use App\Models\CoachingCourse;
use App\Models\CoachingProgress;
use App\Models\CoachingSession;
use App\Models\CoachingSessionMaterial;
use App\Models\SystemAccount;
use App\Support\Coaching\CoachingSessionScope;
use App\Support\Coaching\SafeEmbedUrl;
use App\Support\Enums\CoachingAssignmentStatus;
use App\Support\Enums\CoachingMaterialType;
use App\Support\Enums\CoachingSessionStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoachingSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $account = $request->user();
        $query = CoachingSessionScope::forAccount($account)
            ->with(['course:id,name,code,student_id,coach_id'])
            ->withCount(['materials', 'assignments'])
            ->orderByDesc('date')
            ->orderByDesc('session_number');

        if ($courseId = $request->query('course')) {
            $query->where('course_id', (int) $courseId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        $coursesQuery = CoachingCourse::query()->orderBy('name');
        if ($account->role->value === 'member' && $account->employee_id) {
            $coursesQuery->where('student_id', $account->employee_id);
        }
        $coursesForFilter = $coursesQuery->get(['id', 'name', 'code'])->map(fn (CoachingCourse $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'can' => ['update' => $account->can('update', $c)],
        ]);

        $selectedCourse = null;
        if ($courseId) {
            $course = CoachingCourse::query()->find((int) $courseId);
            if ($course && $account->can('view', $course)) {
                $selectedCourse = [
                    'id' => $course->id,
                    'name' => $course->name,
                    'code' => $course->code,
                    'can' => ['update' => $account->can('update', $course)],
                    'next_session_number' => ((int) $course->sessions()->max('session_number')) + 1,
                ];
            }
        }

        return Inertia::render('Coaching/Sessions/Index', [
            'sessions' => CoachingSessionResource::collection(
                $query->paginate($perPage)->withQueryString(),
            ),
            'filters' => (object) array_merge(
                $request->only(['q', 'status', 'course']),
                ['per_page' => $perPage],
            ),
            'options' => [
                'statuses' => CoachingSessionStatus::options(),
                'courses' => $coursesForFilter,
            ],
            'selectedCourse' => $selectedCourse,
        ]);
    }

    public function schedule(Request $request): Response
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $account = $request->user();
        $month = $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$year, $mon] = array_map('intval', explode('-', $month));
        $start = sprintf('%04d-%02d-01', $year, $mon);
        $end = date('Y-m-t', strtotime($start));

        $sessions = CoachingSessionScope::forAccount($account)
            ->with(['course:id,name,code'])
            ->whereNotNull('date')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('session_number')
            ->get();

        return Inertia::render('Coaching/Sessions/Schedule', [
            'sessions' => CoachingSessionResource::collection($sessions)->resolve(),
            'month' => $month,
        ]);
    }

    public function show(CoachingSession $session): Response
    {
        $session->load(['course', 'materials', 'assignments']);
        $this->authorize('view', $session->course);

        return Inertia::render('Coaching/Sessions/Show', [
            'session' => (new CoachingSessionResource($session))->resolve(),
            'course' => [
                'id' => $session->course->id,
                'name' => $session->course->name,
                'code' => $session->course->code,
            ],
            'materialTypes' => array_map(fn (CoachingMaterialType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], CoachingMaterialType::cases()),
            'can' => [
                'update' => request()->user()->can('update', $session->course),
            ],
        ]);
    }

    public function update(Request $request, CoachingSession $session): RedirectResponse
    {
        $this->authorize('update', $session->course);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string', 'max:8'],
            'end_time' => ['nullable', 'string', 'max:8'],
            'total_hours' => ['nullable', 'numeric', 'min:0'],
            'topic' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(CoachingSessionStatus::values())],
        ]);

        if (
            isset($data['start_time'], $data['end_time'])
            && $data['start_time']
            && $data['end_time']
            && empty($data['total_hours'])
        ) {
            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            if ($start && $end && $end > $start) {
                $data['total_hours'] = round(($end - $start) / 3600, 2);
            }
        }

        $session->update($data);

        if (
            isset($data['status'])
            && $data['status'] === CoachingSessionStatus::Completed->value
        ) {
            $this->syncProgressOnSessionCompleted($session->fresh(['course']));
        }

        return back()->with('success', 'Đã cập nhật buổi học.');
    }

    private function syncProgressOnSessionCompleted(CoachingSession $session): void
    {
        $course = $session->course;
        if (! $course?->student_id) {
            return;
        }

        $accountId = SystemAccount::query()
            ->where('employee_id', $course->student_id)
            ->value('id');

        if (! $accountId) {
            return;
        }

        CoachingProgress::query()->updateOrCreate(
            [
                'course_id' => $course->id,
                'session_id' => $session->id,
                'system_account_id' => $accountId,
            ],
            [
                'is_viewed' => true,
                'is_completed' => true,
                'is_in_progress' => false,
                'updated_at' => now(),
            ],
        );
    }

    public function storeMaterial(Request $request, CoachingSession $session): RedirectResponse
    {
        $this->authorize('update', $session->course);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(CoachingMaterialType::values())],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:1000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        if (! empty($data['url']) && ! SafeEmbedUrl::isAllowed($data['url'])) {
            return back()->withErrors(['url' => 'Liên kết không nằm trong danh sách cho phép (YouTube, Loom, Canva, Google).']);
        }

        $path = null;
        $mime = null;
        $size = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('coaching/sessions/'.$session->id, 'public');
            $mime = $file->getMimeType();
            $size = $file->getSize();
        }

        $session->materials()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'path' => $path,
            'mime_type' => $mime,
            'size' => $size,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm tài liệu.');
    }

    public function materialFile(CoachingSessionMaterial $material): StreamedResponse
    {
        $material->loadMissing('session.course');
        $this->authorize('view', $material->session->course);

        if (! $material->path || ! Storage::disk('public')->exists($material->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $material->path,
            $material->title,
            ['Content-Type' => $material->mime_type ?? 'application/octet-stream'],
        );
    }

    public function storeAssignment(Request $request, CoachingSession $session): RedirectResponse
    {
        $this->authorize('update', $session->course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', Rule::in(TaskPriority::values())],
        ]);

        $session->assignments()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'priority' => $data['priority'] ?? TaskPriority::Medium->value,
            'status' => CoachingAssignmentStatus::Todo->value,
        ]);

        return back()->with('success', 'Đã thêm bài tập.');
    }

    public function updateAssignment(Request $request, CoachingAssignment $assignment): RedirectResponse
    {
        $assignment->loadMissing('session.course');
        $course = $assignment->session->course;
        $user = $request->user();

        $canCoach = $user->can('update', $course);
        $canStudent = $user->employee_id === $course->student_id;

        abort_unless($canCoach || $canStudent, 403);

        $rules = [
            'status' => ['sometimes', 'string', Rule::in(CoachingAssignmentStatus::values())],
            'notes' => ['nullable', 'string'],
            'github_url' => ['nullable', 'url', 'max:500'],
        ];

        if ($canCoach) {
            $rules['title'] = ['sometimes', 'string', 'max:500'];
            $rules['description'] = ['nullable', 'string'];
            $rules['deadline'] = ['nullable', 'date'];
        }

        $data = $request->validate($rules);

        if ($request->hasFile('submission') && $canStudent) {
            $file = $request->file('submission');
            $data['submission_path'] = $file->store('coaching/assignments/'.$assignment->id, 'public');
        }

        $assignment->update($data);

        return back()->with('success', 'Đã cập nhật bài tập.');
    }

    public function upsertProgress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:coaching_courses,id'],
            'session_id' => ['required', 'integer', 'exists:coaching_sessions,id'],
            'is_viewed' => ['sometimes', 'boolean'],
            'is_in_progress' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        $course = CoachingCourse::findOrFail($data['course_id']);
        $this->authorize('view', $course);

        CoachingProgress::query()->updateOrCreate(
            [
                'course_id' => $data['course_id'],
                'session_id' => $data['session_id'],
                'system_account_id' => $request->user()->id,
            ],
            [
                'is_viewed' => $data['is_viewed'] ?? false,
                'is_in_progress' => $data['is_in_progress'] ?? false,
                'is_completed' => $data['is_completed'] ?? false,
                'updated_at' => now(),
            ],
        );

        return back();
    }
}
