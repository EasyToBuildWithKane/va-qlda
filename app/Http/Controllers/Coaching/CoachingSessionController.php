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
use App\Support\Coaching\CoachingSessionIndexQuery;
use App\Support\Coaching\CoachingSessionScope;
use App\Support\Coaching\SafeEmbedUrl;
use App\Support\Enums\CoachingAssignmentStatus;
use App\Support\Enums\CoachingMaterialType;
use App\Support\Enums\CoachingSessionStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Http\JsonResponse;
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
        $query = CoachingSessionIndexQuery::for($request, $account);

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

        $courseId = $request->query('course');
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
                (clone $query)->paginate($perPage)->withQueryString(),
            ),
            'summary' => CoachingSessionIndexQuery::summarize($query),
            'filters' => (object) array_merge(
                $request->only(['q', 'status', 'course', 'date_from', 'date_to', 'has_materials', 'has_assignments', 'scheduled']),
                ['per_page' => $perPage],
            ),
            'options' => [
                'statuses' => CoachingSessionStatus::options(),
                'courses' => $coursesForFilter,
            ],
            'selectedCourse' => $selectedCourse,
        ]);
    }

    public function exportIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $query = CoachingSessionIndexQuery::for($request, $request->user());
        $totalMatching = (clone $query)->count('coaching_sessions.id');
        $limit = 500;
        $sessions = (clone $query)->limit($limit)->get();

        return response()->json([
            'data' => CoachingSessionResource::collection($sessions)->resolve(),
            'meta' => [
                'total_matching' => $totalMatching,
                'exported' => $sessions->count(),
                'truncated' => $totalMatching > $limit,
                'limit' => $limit,
            ],
        ]);
    }

    public function schedule(Request $request): Response
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $account = $request->user();

        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $statsQuery = CoachingSessionScope::forAccount($account)->whereNotNull('date');
        $todayCount = (clone $statsQuery)->whereDate('date', $today)->count();
        $weekCount = (clone $statsQuery)->whereBetween('date', [$weekStart, $weekEnd])->count();

        $coursesQuery = CoachingCourse::query()->orderBy('name');
        if ($account->role->value === 'member' && $account->employee_id) {
            $coursesQuery->where('student_id', $account->employee_id);
        }

        $courses = $coursesQuery
            ->get(['id', 'code', 'name', 'coach_id', 'coach_name', 'student_id', 'student_name'])
            ->map(fn (CoachingCourse $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'coach_id' => $c->coach_id,
                'coach_name' => $c->coach_name,
                'student_name' => $c->student_name,
                'can' => ['manage' => $account->can('update', $c)],
            ]);

        $coaches = $courses
            ->filter(fn ($c) => (bool) $c['coach_name'])
            ->groupBy('coach_name')
            ->map(fn ($group, $name) => [
                'id' => $group->first()['coach_id'],
                'name' => $name,
                'courses_count' => $group->count(),
            ])
            ->values();

        return Inertia::render('Coaching/Sessions/Schedule', [
            'stats' => [
                'today' => $todayCount,
                'week' => $weekCount,
                'coaches' => $coaches->count(),
            ],
            'coaches' => $coaches,
            'courses' => $courses,
            'statuses' => CoachingSessionStatus::options(),
            'can' => [
                'manage' => $courses->contains(fn ($c) => $c['can']['manage']),
            ],
        ]);
    }

    /**
     * JSON feed for FullCalendar — range-bounded so it scales to 1000+ events.
     */
    public function calendarFeed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $account = $request->user();
        $start = $request->query('start');
        $end = $request->query('end');

        $query = CoachingSessionScope::forAccount($account)
            ->with(['course:id,code,name,coach_id,coach_name,student_id,student_name'])
            ->whereNotNull('date');

        if (is_string($start) && $start !== '') {
            $query->whereDate('date', '>=', substr($start, 0, 10));
        }
        if (is_string($end) && $end !== '') {
            $query->whereDate('date', '<=', substr($end, 0, 10));
        }

        $events = $query
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (CoachingSession $s) => $this->toCalendarEvent($s, $account))
            ->values();

        return response()->json($events);
    }

    /**
     * Quick-create a session straight from the calendar (clicked slot).
     */
    public function calendarStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:coaching_courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'string', 'max:8'],
            'end_time' => ['nullable', 'string', 'max:8'],
            'total_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'topic' => ['nullable', 'string', 'max:500'],
        ], [
            'title.required' => 'Vui lòng nhập tên buổi học.',
            'course_id.required' => 'Vui lòng chọn khóa coaching.',
            'date.required' => 'Vui lòng chọn ngày.',
        ]);

        $course = CoachingCourse::findOrFail($data['course_id']);
        $this->authorize('update', $course);

        $data['total_hours'] = $this->resolveTotalHours(
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            $data['total_hours'] ?? null,
        );

        $session = $course->sessions()->create([
            'title' => $data['title'],
            'session_number' => ((int) $course->sessions()->max('session_number')) + 1,
            'date' => $data['date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'total_hours' => $data['total_hours'],
            'topic' => $data['topic'] ?? null,
            'status' => CoachingSessionStatus::Pending->value,
        ]);

        $session->load(['course:id,code,name,coach_id,coach_name,student_id,student_name']);

        return response()->json([
            'ok' => true,
            'event' => $this->toCalendarEvent($session, $request->user()),
        ], 201);
    }

    /**
     * Drag-reschedule, resize and inline status/field edits — returns the updated event.
     */
    public function calendarUpdate(Request $request, CoachingSession $session): JsonResponse
    {
        $this->authorize('update', $session->course);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string', 'max:8'],
            'end_time' => ['nullable', 'string', 'max:8'],
            'total_hours' => ['nullable', 'numeric', 'min:0'],
            'topic' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(CoachingSessionStatus::values())],
        ]);

        $this->applySessionUpdate($session, $data);

        $session->refresh()->load(['course:id,code,name,coach_id,coach_name,student_id,student_name']);

        return response()->json([
            'ok' => true,
            'event' => $this->toCalendarEvent($session, $request->user()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toCalendarEvent(CoachingSession $session, SystemAccount $account): array
    {
        $date = $session->date?->toDateString();
        $course = $session->course;

        $start = $date;
        $end = null;
        if ($date && $session->start_time) {
            $start = $date.'T'.$this->normalizeTime($session->start_time);
            if ($session->end_time) {
                $end = $date.'T'.$this->normalizeTime($session->end_time);
            } elseif ($session->total_hours) {
                $ts = strtotime($date.' '.$session->start_time);
                if ($ts) {
                    $end = date('Y-m-d\TH:i:s', $ts + (int) round(((float) $session->total_hours) * 3600));
                }
            }
        }

        return [
            'id' => (string) $session->id,
            'title' => $session->title,
            'start' => $start,
            'end' => $end,
            'allDay' => $session->start_time === null,
            'extendedProps' => [
                'sessionId' => $session->id,
                'sessionNumber' => $session->session_number,
                'status' => $session->status->value,
                'statusLabel' => $session->status->label(),
                'courseId' => $course?->id,
                'courseCode' => $course?->code,
                'courseName' => $course?->name,
                'coachId' => $course?->coach_id,
                'coachName' => $course?->coach_name,
                'studentName' => $course?->student_name,
                'topic' => $session->topic,
                'notes' => $session->notes,
                'durationHours' => $session->total_hours !== null ? (float) $session->total_hours : null,
                'startTime' => $session->start_time ? substr($session->start_time, 0, 5) : null,
                'endTime' => $session->end_time ? substr($session->end_time, 0, 5) : null,
                'detailUrl' => route('coaching.sessions.show', ['session' => $session->id]),
                'canManage' => $account->can('update', $course),
            ],
        ];
    }

    private function normalizeTime(string $time): string
    {
        return substr($time, 0, 5).':00';
    }

    private function resolveTotalHours(?string $startTime, ?string $endTime, ?float $hours): ?float
    {
        if ($hours !== null && $hours > 0) {
            return $hours;
        }
        if ($startTime && $endTime) {
            $start = strtotime($startTime);
            $end = strtotime($endTime);
            if ($start && $end && $end > $start) {
                return round(($end - $start) / 3600, 2);
            }
        }

        return $hours;
    }

    public function show(CoachingSession $session): Response
    {
        $session->load(['course', 'materials', 'assignments']);
        $this->authorize('view', $session->course);

        $user = request()->user();
        $course = $session->course;
        $isStudent = $user->employee_id !== null && $user->employee_id === $course->student_id;

        return Inertia::render('Coaching/Sessions/Show', [
            'session' => (new CoachingSessionResource($session))->resolve(),
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
            ],
            'materialTypes' => array_map(fn (CoachingMaterialType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], CoachingMaterialType::cases()),
            'can' => [
                'update' => $user->can('update', $course),
                'manageAssignments' => $user->can('update', $course),
                'completeAssignments' => $user->can('update', $course) || $isStudent,
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

        $this->applySessionUpdate($session, $data);

        return back()->with('success', 'Đã cập nhật buổi học.');
    }

    /**
     * Shared mutation for session edits (auto-derives hours, syncs progress on completion).
     *
     * @param  array<string, mixed>  $data
     */
    private function applySessionUpdate(CoachingSession $session, array $data): void
    {
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

        if (isset($data['title'])) {
            $data['topic'] = $data['title'];
        }

        $session->update($data);

        if (
            isset($data['status'])
            && $data['status'] === CoachingSessionStatus::Completed->value
        ) {
            $this->syncProgressOnSessionCompleted($session->fresh(['course']));
        }
    }

    public function destroy(CoachingSession $session): RedirectResponse
    {
        $session->loadMissing('course');
        $this->authorize('update', $session->course);
        $session->delete();

        return redirect()
            ->route('coaching.sessions.index')
            ->with('success', 'Đã xóa buổi học.');
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

        $nextStatus = $data['status'] ?? $assignment->status->value;
        if ($nextStatus === CoachingAssignmentStatus::Done->value) {
            $notes = trim((string) ($data['notes'] ?? $assignment->notes ?? ''));
            if ($notes === '') {
                return back()->withErrors([
                    'notes' => 'Vui lòng nhập nội dung hoàn thành.',
                ]);
            }
            $data['notes'] = $notes;
        }

        if ($request->hasFile('submission') && $canStudent) {
            $file = $request->file('submission');
            $data['submission_path'] = $file->store('coaching/assignments/'.$assignment->id, 'public');
        }

        $assignment->update($data);

        return back()->with('success', 'Đã cập nhật bài tập.');
    }

    public function destroyAssignment(CoachingAssignment $assignment): RedirectResponse
    {
        $assignment->loadMissing('session.course');
        $this->authorize('update', $assignment->session->course);

        if ($assignment->submission_path) {
            Storage::disk('public')->delete($assignment->submission_path);
        }

        $assignment->delete();

        return back()->with('success', 'Đã xóa bài tập.');
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
