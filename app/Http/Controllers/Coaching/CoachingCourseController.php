<?php

namespace App\Http\Controllers\Coaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\StoreCoachingCourseRequest;
use App\Http\Requests\Coaching\StoreCoachingSessionRequest;
use App\Http\Requests\Coaching\UpdateCoachingCourseRequest;
use App\Http\Resources\CoachingCourseResource;
use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use App\Support\Enums\CoachingCourseStatus;
use App\Support\Enums\CoachingSessionStatus;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachingCourseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $account = $request->user();
        $query = CoachingCourse::query()
            ->with(['student', 'coach'])
            ->withCount([
                'sessions',
                'sessions as completed_sessions_count' => fn ($q) => $q->where(
                    'status',
                    CoachingSessionStatus::Completed->value,
                ),
            ])
            ->latest();

        if ($account->role->value === 'member' && $account->employee_id) {
            $query->where('student_id', $account->employee_id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('student_name', 'like', "%{$search}%")
                ->orWhere('coach_name', 'like', "%{$search}%"));
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        return Inertia::render('Coaching/Courses/Index', [
            'courses' => CoachingCourseResource::collection($query->paginate($perPage)->withQueryString()),
            'filters' => (object) array_merge(
                $request->only(['q', 'status']),
                ['per_page' => $perPage],
            ),
            'options' => [
                'statuses' => CoachingCourseStatus::options(),
            ],
            'can' => ['create' => $account->can('create', CoachingCourse::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CoachingCourse::class);

        return Inertia::render('Coaching/Courses/Edit', [
            'course' => null,
            'options' => ['statuses' => CoachingCourseStatus::options()],
        ]);
    }

    public function store(StoreCoachingCourseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $course = CoachingCourse::create($data);

        SecurityAuditLogger::coachingCourse($request->user(), 'created', $course->id, ['name' => $course->name]);

        return redirect()
            ->route('coaching.courses.show', $course)
            ->with('success', 'Đã tạo khóa học.');
    }

    public function show(CoachingCourse $course): Response
    {
        $this->authorize('view', $course);

        $course->load(['student', 'coach'])->loadCount('sessions');
        $course->progress_percent_computed = $course->progressPercent();

        return Inertia::render('Coaching/Courses/Show', [
            'course' => (new CoachingCourseResource($course))->resolve(),
        ]);
    }

    public function edit(CoachingCourse $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('Coaching/Courses/Edit', [
            'course' => (new CoachingCourseResource($course))->resolve(),
            'options' => ['statuses' => CoachingCourseStatus::options()],
        ]);
    }

    public function update(UpdateCoachingCourseRequest $request, CoachingCourse $course): RedirectResponse
    {
        $course->update($request->validated());

        SecurityAuditLogger::coachingCourse($request->user(), 'updated', $course->id, ['name' => $course->name]);

        return redirect()
            ->route('coaching.courses.show', $course)
            ->with('success', 'Đã cập nhật khóa học.');
    }

    public function destroy(CoachingCourse $course): RedirectResponse
    {
        $this->authorize('delete', $course);
        SecurityAuditLogger::coachingCourse(request()->user(), 'deleted', $course->id, ['name' => $course->name]);
        $course->delete();

        return redirect()
            ->route('coaching.courses.index')
            ->with('success', 'Đã xóa khóa học.');
    }

    public function storeSession(StoreCoachingSessionRequest $request, CoachingCourse $course): RedirectResponse
    {
        $data = $request->validated();
        $data['course_id'] = $course->id;
        $data['status'] ??= CoachingSessionStatus::Pending->value;

        $session = CoachingSession::create($data);

        SecurityAuditLogger::coachingSession($request->user(), 'created', $session->id, [
            'course_id' => $course->id,
            'title' => $session->title,
        ]);

        return redirect()
            ->route('coaching.sessions.index', ['course' => $course->id])
            ->with('success', 'Đã thêm buổi học.');
    }
}
