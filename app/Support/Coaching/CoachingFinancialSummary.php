<?php

namespace App\Support\Coaching;

use App\Models\CoachingCourse;
use App\Models\CoachingSession;
use App\Support\Enums\CoachingSessionStatus;
use Carbon\Carbon;

class CoachingFinancialSummary
{
    /**
     * @return array{
     *   sessions_total:int,
     *   sessions_completed:int,
     *   sessions_cancelled:int,
     *   hours_total:float,
     *   revenue_total:float,
     *   avg_per_hour:float|null,
     *   avg_per_session:float|null,
     *   students_distinct:int
     * }
     */
    public static function forMonth(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $sessions = CoachingSession::query()
            ->with('course')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $completed = $sessions->where('status', CoachingSessionStatus::Completed);
        $cancelled = $sessions->where('status', CoachingSessionStatus::Cancelled);

        $hours = (float) $completed->sum(fn (CoachingSession $s) => (float) ($s->total_hours ?? 0));

        $revenue = 0.0;
        foreach ($completed as $session) {
            $course = $session->course;
            if (! $course) {
                continue;
            }
            if ($course->hourly_rate && $session->total_hours) {
                $revenue += (float) $course->hourly_rate * (float) $session->total_hours;
            }
        }

        $completedCount = $completed->count();

        $coursesInMonth = $sessions
            ->map(fn (CoachingSession $s) => $s->course)
            ->filter()
            ->unique('id')
            ->values();

        if ($coursesInMonth->isEmpty()) {
            $coursesInMonth = CoachingCourse::query()
                ->where(function ($q) use ($end) {
                    $q->whereNull('start_date')
                        ->orWhere('start_date', '<=', $end->toDateString());
                })
                ->where(function ($q) use ($start) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $start->toDateString());
                })
                ->get(['id', 'student_id', 'student_name']);
        }

        return [
            'sessions_total' => $sessions->count(),
            'sessions_completed' => $completedCount,
            'sessions_cancelled' => $cancelled->count(),
            'hours_total' => round($hours, 2),
            'revenue_total' => round($revenue, 2),
            'avg_per_hour' => $hours > 0 ? round($revenue / $hours, 2) : null,
            'avg_per_session' => $completedCount > 0 ? round($revenue / $completedCount, 2) : null,
            'students_distinct' => CoachingStudentMetrics::countDistinct($coursesInMonth),
        ];
    }

    /**
     * @return array<int, array{month:string, revenue:float, hours:float}>
     */
    public static function revenueSeries(int $months = 12): array
    {
        $out = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $summary = self::forMonth((int) $cursor->year, (int) $cursor->month);
            $out[] = [
                'month' => $cursor->format('Y-m'),
                'revenue' => $summary['revenue_total'],
                'hours' => $summary['hours_total'],
            ];
            $cursor->subMonth();
        }

        return array_reverse($out);
    }
}
