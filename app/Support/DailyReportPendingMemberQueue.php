<?php

namespace App\Support;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Aggregates the review queue by employee for /daily-reports/review.
 */
final class DailyReportPendingMemberQueue
{
    /**
     * @return array{
     *     members: list<array<string, mixed>>,
     *     totals: array{reports: int, members: int, today: int, late: int}
     * }
     */
    public static function build(?string $today = null): array
    {
        $today = $today ?? DailyReportCalendar::today();

        $rows = DailyReport::query()
            ->pendingReview()
            ->select('employee_id')
            ->selectRaw('COUNT(*) as pending_count')
            ->selectRaw('MIN(date) as oldest_date')
            ->selectRaw('MAX(date) as newest_date')
            ->selectRaw('MAX(submitted_at) as latest_submitted_at')
            ->groupBy('employee_id')
            ->orderByDesc('latest_submitted_at')
            ->get();

        $employeeIds = $rows->pluck('employee_id')->filter()->unique()->values();
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->get(['id', 'full_name', 'role_title', 'avatar_path', 'meta'])
            ->keyBy('id');

        $members = $rows->map(function ($row) use ($employees) {
            $emp = $employees->get($row->employee_id);

            $oldest = $row->oldest_date instanceof Carbon
                ? $row->oldest_date->toDateString()
                : (string) $row->oldest_date;
            $newest = $row->newest_date instanceof Carbon
                ? $row->newest_date->toDateString()
                : (string) $row->newest_date;
            $latestSubmitted = $row->latest_submitted_at instanceof Carbon
                ? $row->latest_submitted_at
                : ($row->latest_submitted_at ? Carbon::parse($row->latest_submitted_at) : null);

            $meta = is_array($emp?->meta) ? $emp->meta : [];

            return [
                'employee_id' => (int) $row->employee_id,
                'name' => $emp?->full_name ?? 'Chưa cập nhật',
                'role_title' => $emp?->role_title,
                'avatar_path' => PublicMediaUrl::fromPublicDisk($emp?->avatar_path),
                'department_code' => filled($meta['department_code'] ?? null)
                    ? (string) $meta['department_code']
                    : null,
                'department_name' => filled($meta['department_name'] ?? null)
                    ? (string) $meta['department_name']
                    : (filled($meta['department'] ?? null) ? (string) $meta['department'] : null),
                'pending_count' => (int) $row->pending_count,
                'oldest_date' => $oldest,
                'newest_date' => $newest,
                'latest_submitted_at' => $latestSubmitted?->toIso8601String(),
            ];
        })->values()->all();

        $base = DailyReport::query()->pendingReview();

        return [
            'members' => $members,
            'totals' => [
                'reports' => (int) (clone $base)->count(),
                'members' => count($members),
                'today' => (int) (clone $base)->whereDate('date', $today)->count(),
                'late' => (int) (clone $base)->where('is_late', true)->count(),
            ],
        ];
    }
}
