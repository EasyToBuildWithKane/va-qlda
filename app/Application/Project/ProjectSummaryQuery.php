<?php

namespace App\Application\Project;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectStatus;
use App\Support\Project\ProjectVisibility;
use Illuminate\Support\Carbon;

class ProjectSummaryQuery
{
    /**
     * Portfolio KPI counts in a single aggregate query.
     *
     * @return array{total: int, active: int, completed: int, overdue: int}
     */
    public function execute(?SystemAccount $account = null): array
    {
        $today = Carbon::today()->toDateString();
        $completed = ProjectStatus::Completed->value;
        $cancelled = ProjectStatus::Cancelled->value;
        $active = ProjectStatus::Active->value;

        $query = Project::query();
        if ($account !== null) {
            ProjectVisibility::constrainIndex($query, $account);
        }

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_count', [$active])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [$completed])
            ->selectRaw(
                'SUM(CASE WHEN due_date IS NOT NULL AND date(due_date) < ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue_count',
                [$today, $completed, $cancelled],
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'completed' => (int) ($row->completed_count ?? 0),
            'overdue' => (int) ($row->overdue_count ?? 0),
        ];
    }
}
