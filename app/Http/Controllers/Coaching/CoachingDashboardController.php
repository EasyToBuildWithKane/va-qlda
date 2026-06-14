<?php

namespace App\Http\Controllers\Coaching;

use App\Http\Controllers\Controller;
use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use App\Support\Coaching\CoachingFinancialSummary;
use App\Support\Coaching\CoachingStudentMetrics;
use App\Support\Enums\CoachingCourseStatus;
use App\Support\Enums\CoachingSessionStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachingDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', CoachingCourse::class);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $monthly = CoachingFinancialSummary::forMonth($year, $month);

        $activeCourses = CoachingCourse::query()
            ->with(['student', 'coach'])
            ->withCount('sessions')
            ->where('status', CoachingCourseStatus::Active->value)
            ->get()
            ->map(fn (CoachingCourse $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'progress_percent' => $c->progressPercent(),
            ]);

        return Inertia::render('Coaching/Dashboard', [
            'summary' => [
                'courses_total' => CoachingCourse::count(),
                'courses_active' => CoachingCourse::where('status', CoachingCourseStatus::Active->value)->count(),
                'students_distinct' => CoachingStudentMetrics::countDistinctInDatabase(),
                'sessions_total' => CoachingSession::count(),
                'hours_total' => (float) CoachingSession::where('status', CoachingSessionStatus::Completed->value)->sum('total_hours'),
            ],
            'monthly' => $monthly,
            'month' => sprintf('%04d-%02d', $year, $month),
            'revenueSeries' => CoachingFinancialSummary::revenueSeries(12),
            'dailySeries' => CoachingFinancialSummary::dailySeries($year, $month),
            'activeCourses' => $activeCourses,
            'can' => [
                'create' => $request->user()->can('create', CoachingCourse::class),
                'export' => $request->user()->can('exportReport', CoachingCourse::class),
            ],
        ]);
    }
}
