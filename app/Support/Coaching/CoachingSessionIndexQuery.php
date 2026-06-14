<?php

namespace App\Support\Coaching;

use App\Models\SystemAccount;
use App\Support\Enums\CoachingSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CoachingSessionIndexQuery
{
    /**
     * @return Builder<\App\Models\CoachingSession>
     */
    public static function for(Request $request, SystemAccount $account): Builder
    {
        $query = CoachingSessionScope::forAccount($account)
            ->with([
                'course' => fn ($q) => $q
                    ->select('id', 'name', 'code', 'student_name', 'coach_name', 'student_id', 'coach_id')
                    ->with([
                        'student:id,full_name',
                        'coach:id,full_name',
                    ]),
            ])
            ->join('coaching_courses as coaching_courses_sort', 'coaching_courses_sort.id', '=', 'coaching_sessions.course_id')
            ->select('coaching_sessions.*')
            ->withCount(['materials', 'assignments'])
            ->orderBy('coaching_courses_sort.name')
            ->orderByDesc('coaching_sessions.session_number');

        if ($courseId = $request->query('course')) {
            $query->where('coaching_sessions.course_id', (int) $courseId);
        }

        if ($status = $request->query('status')) {
            $query->where('coaching_sessions.status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('coaching_sessions.title', 'like', "%{$search}%")
                    ->orWhere('coaching_sessions.topic', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('coaching_sessions.date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('coaching_sessions.date', '<=', $dateTo);
        }

        if ($request->query('has_materials') === '1') {
            $query->has('materials');
        } elseif ($request->query('has_materials') === '0') {
            $query->doesntHave('materials');
        }

        if ($request->query('has_assignments') === '1') {
            $query->has('assignments');
        } elseif ($request->query('has_assignments') === '0') {
            $query->doesntHave('assignments');
        }

        if ($request->query('scheduled') === '1') {
            $query->whereNotNull('coaching_sessions.date');
        } elseif ($request->query('scheduled') === '0') {
            $query->whereNull('coaching_sessions.date');
        }

        return $query;
    }

    /**
     * @return array{
     *     total: int,
     *     courses: int,
     *     hours_total: float,
     *     completed: int,
     *     in_progress: int,
     *     pending: int,
     *     cancelled: int,
     *     unscheduled: int,
     *     with_materials: int,
     *     with_assignments: int
     * }
     */
    public static function summarize(Builder $query): array
    {
        $base = clone $query;

        $total = (clone $base)->count('coaching_sessions.id');
        $courses = (clone $base)->distinct('coaching_sessions.course_id')->count('coaching_sessions.course_id');
        $hoursTotal = (float) ((clone $base)->sum('coaching_sessions.total_hours') ?? 0);

        $byStatus = static fn (CoachingSessionStatus $status) => (clone $base)
            ->where('coaching_sessions.status', $status->value)
            ->count('coaching_sessions.id');

        return [
            'total' => $total,
            'courses' => $courses,
            'hours_total' => round($hoursTotal, 2),
            'completed' => $byStatus(CoachingSessionStatus::Completed),
            'in_progress' => $byStatus(CoachingSessionStatus::InProgress),
            'pending' => $byStatus(CoachingSessionStatus::Pending),
            'cancelled' => $byStatus(CoachingSessionStatus::Cancelled),
            'unscheduled' => (clone $base)->whereNull('coaching_sessions.date')->count('coaching_sessions.id'),
            'with_materials' => (clone $base)->has('materials')->count('coaching_sessions.id'),
            'with_assignments' => (clone $base)->has('assignments')->count('coaching_sessions.id'),
        ];
    }
}
