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
     * @return array<int, array{month:string, revenue:float, hours:float, sessions_total:int, sessions_completed:int}>
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
                'sessions_total' => $summary['sessions_total'],
                'sessions_completed' => $summary['sessions_completed'],
            ];
            $cursor->subMonth();
        }

        return array_reverse($out);
    }

    /**
     * @return array<int, array{day:string, label:string, weekday:string, revenue:float, hours:float, sessions:int}>
     */
    public static function dailySeries(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $completed = CoachingSession::query()
            ->with('course')
            ->where('status', CoachingSessionStatus::Completed->value)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $revenueByDay = [];
        $hoursByDay = [];
        $sessionsByDay = [];

        foreach ($completed as $session) {
            $day = $session->date?->toDateString();
            if (! $day) {
                continue;
            }
            $hours = (float) ($session->total_hours ?? 0);
            $hoursByDay[$day] = ($hoursByDay[$day] ?? 0) + $hours;
            $sessionsByDay[$day] = ($sessionsByDay[$day] ?? 0) + 1;

            $course = $session->course;
            if ($course && $course->hourly_rate && $hours > 0) {
                $revenueByDay[$day] = ($revenueByDay[$day] ?? 0) + (float) $course->hourly_rate * $hours;
            }
        }

        $out = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $out[] = [
                'day' => $key,
                'label' => $cursor->format('d/m'),
                'weekday' => $cursor->locale('vi')->isoFormat('dddd'),
                'revenue' => round($revenueByDay[$key] ?? 0, 2),
                'hours' => round($hoursByDay[$key] ?? 0, 2),
                'sessions' => (int) ($sessionsByDay[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Tổng hợp theo tuần (bắt đầu thứ Hai) trong phạm vi tháng đang xem.
     *
     * @return array<int, array{week_start:string, week_end:string, label:string, revenue:float, hours:float, sessions:int, days_with_activity:int}>
     */
    public static function weeklySeries(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daily = self::dailySeries($year, $month);

        $buckets = [];
        foreach ($daily as $row) {
            $day = Carbon::parse($row['day']);
            $weekStart = $day->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::MONDAY);
            $key = $weekStart->toDateString();

            if (! isset($buckets[$key])) {
                $clipStart = $weekStart->lt($start) ? $start->copy() : $weekStart->copy();
                $clipEnd = $weekEnd->gt($end) ? $end->copy() : $weekEnd->copy();
                $buckets[$key] = [
                    'week_start' => $clipStart->toDateString(),
                    'week_end' => $clipEnd->toDateString(),
                    'label' => sprintf(
                        'Tuần %s – %s',
                        $clipStart->format('d/m'),
                        $clipEnd->format('d/m/Y')
                    ),
                    'revenue' => 0.0,
                    'hours' => 0.0,
                    'sessions' => 0,
                    'days_with_activity' => 0,
                ];
            }

            $buckets[$key]['revenue'] += (float) $row['revenue'];
            $buckets[$key]['hours'] += (float) $row['hours'];
            $buckets[$key]['sessions'] += (int) $row['sessions'];
            if ($row['sessions'] > 0 || $row['hours'] > 0 || $row['revenue'] > 0) {
                $buckets[$key]['days_with_activity'] += 1;
            }
        }

        $out = array_values($buckets);
        usort($out, fn (array $a, array $b) => strcmp($a['week_start'], $b['week_start']));

        foreach ($out as &$row) {
            $row['revenue'] = round($row['revenue'], 2);
            $row['hours'] = round($row['hours'], 2);
        }
        unset($row);

        return $out;
    }
}
