<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // ---- Task counts per member (by status) -------------------------
        $taskRows = Task::select(
                'assignee_id',
                'status',
                DB::raw('count(*) as total')
            )
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id', 'status')
            ->get();

        // ---- Worklog hours per member (last 30 days) --------------------
        $worklogRows = DB::table('va_prd_worklogs')
            ->join('va_prd_tasks', 'va_prd_worklogs.task_id', '=', 'va_prd_tasks.id')
            ->whereDate('va_prd_worklogs.logged_at', '>=', now()->subDays(29))
            ->whereNotNull('va_prd_tasks.assignee_id')
            ->select(
                'va_prd_tasks.assignee_id',
                DB::raw('SUM(va_prd_worklogs.hours) as total_hours')
            )
            ->groupBy('va_prd_tasks.assignee_id')
            ->get()
            ->keyBy('assignee_id');

        // ---- Overdue tasks per member ------------------------------------
        $overdueRows = Task::whereNotNull('assignee_id')
            ->where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->select('assignee_id', DB::raw('count(*) as total'))
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        // ---- Build member list with stats --------------------------------
        $members = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email', 'role_title', 'avatar_path'])
            ->map(function (Employee $emp) use ($taskRows, $worklogRows, $overdueRows) {
                $myTasks = $taskRows->where('assignee_id', $emp->id);

                $byStatus = [];
                foreach (TaskStatus::cases() as $s) {
                    $byStatus[$s->value] = (int) ($myTasks->firstWhere('status', $s)?->total ?? 0);
                }

                $total    = array_sum($byStatus);
                $done     = $byStatus[TaskStatus::Done->value];
                $rate     = $total > 0 ? round($done / $total * 100) : 0;

                return [
                    'id'         => $emp->id,
                    'name'       => $emp->full_name,
                    'email'      => $emp->email,
                    'role_title' => $emp->role_title,
                    'avatar'     => $emp->avatar_path,
                    'tasks'      => $byStatus,
                    'total'      => $total,
                    'done'       => $done,
                    'rate'       => $rate,
                    'hours'      => round((float) ($worklogRows->get($emp->id)?->total_hours ?? 0), 1),
                    'overdue'    => (int) ($overdueRows->get($emp->id)?->total ?? 0),
                ];
            });

        // ---- Weekly task completion (last 6 weeks) -----------------------
        $weeklyTrend = Task::where('status', TaskStatus::Done)
            ->whereDate('updated_at', '>=', now()->subWeeks(5)->startOfWeek())
            ->select(
                DB::raw('YEARWEEK(updated_at, 1) as yw'),
                DB::raw('MIN(DATE(updated_at)) as week_start'),
                DB::raw('count(*) as total')
            )
            ->groupBy('yw')
            ->orderBy('yw')
            ->get()
            ->map(fn ($r) => [
                'label' => 'T' . date('W', strtotime($r->week_start)),
                'total' => $r->total,
            ]);

        // ---- Task status distribution per project for top 8 projects -----
        $projectTaskStats = DB::table('va_prd_tasks')
            ->join('va_prd_projects', 'va_prd_tasks.project_id', '=', 'va_prd_projects.id')
            ->select(
                'va_prd_projects.id',
                'va_prd_projects.name',
                'va_prd_tasks.status',
                DB::raw('count(*) as total')
            )
            ->whereIn('va_prd_projects.id', function ($q) {
                $q->select('id')
                    ->from('va_prd_projects')
                    ->where('is_active', true)
                    ->limit(8);
            })
            ->groupBy('va_prd_projects.id', 'va_prd_projects.name', 'va_prd_tasks.status')
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $first = $rows->first();
                $byStatus = [];
                foreach ($rows as $r) {
                    $byStatus[$r->status] = $r->total;
                }

                return [
                    'id'       => $first->id,
                    'name'     => $first->name,
                    'byStatus' => $byStatus,
                    'total'    => array_sum($byStatus),
                ];
            })
            ->values();

        return Inertia::render('Dashboard/Team', [
            'members'          => $members,
            'weeklyTrend'      => $weeklyTrend,
            'projectTaskStats' => $projectTaskStats,
        ]);
    }
}
