<?php

namespace App\Http\Middleware;

use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the coaching workspace sidebar data (courses + active course sessions)
 * with every Inertia coaching page. Scoped to the coaching route group so the
 * queries never run on other parts of the app, and lazily resolved so they only
 * run when an Inertia response is actually being built (not for file downloads).
 */
class ShareCoachingSidebar
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('coaching', fn () => [
            'sidebar' => $this->sidebar($request),
        ]);

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function sidebar(Request $request): array
    {
        $account = $request->user();

        [$activeCourseId, $activeSessionId] = $this->activeIds($request);

        $coursesQuery = CoachingCourse::query()
            ->with('student:id,full_name')
            ->withCount('sessions')
            ->latest();

        if ($account && $account->role->value === 'member' && $account->employee_id) {
            $coursesQuery->where('student_id', $account->employee_id);
        }

        $courses = $coursesQuery->get()->map(fn (CoachingCourse $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'status' => $c->status ? ['value' => $c->status->value, 'label' => $c->status->label()] : null,
            'sessions_count' => $c->sessions_count,
            'student_display' => $c->student_name ?: $c->student?->full_name,
        ])->all();

        $sessions = [];
        if ($activeCourseId) {
            $sessions = CoachingSession::query()
                ->where('course_id', $activeCourseId)
                ->orderBy('session_number')
                ->get(['id', 'course_id', 'session_number', 'title', 'status'])
                ->map(fn (CoachingSession $s) => [
                    'id' => $s->id,
                    'session_number' => $s->session_number,
                    'title' => $s->title,
                    'status' => $s->status ? ['value' => $s->status->value, 'label' => $s->status->label()] : null,
                ])->all();
        }

        return [
            'courses' => $courses,
            'sessions' => $sessions,
            'activeCourseId' => $activeCourseId,
            'activeSessionId' => $activeSessionId,
        ];
    }

    /**
     * Resolve the active course / session from bound route parameters.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function activeIds(Request $request): array
    {
        $course = $request->route('course');
        if ($course instanceof CoachingCourse) {
            return [$course->id, null];
        }

        $session = $request->route('session');
        if ($session instanceof CoachingSession) {
            return [$session->course_id, $session->id];
        }

        return [null, null];
    }
}
