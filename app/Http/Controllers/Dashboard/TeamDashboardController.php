<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Worklog;
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
        $tablePrefix = DB::connection()->getTablePrefix();

        $worklogRows = Worklog::query()
            ->join('tasks', 'worklogs.task_id', '=', 'tasks.id')
            ->whereDate('worklogs.date', '>=', now()->subDays(29))
            ->whereNotNull('tasks.assignee_id')
            ->select('tasks.assignee_id')
            ->selectRaw("SUM({$tablePrefix}worklogs.hours) as total_hours")
            ->groupBy('tasks.assignee_id')
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

                $total = array_sum($byStatus);
                $done = $byStatus[TaskStatus::Done->value];
                $rate = $total > 0 ? round($done / $total * 100) : 0;

                return [
                    'id' => $emp->id,
                    'name' => $emp->full_name,
                    'email' => $emp->email,
                    'role_title' => $emp->role_title,
                    'avatar' => $emp->avatar_path,
                    'tasks' => $byStatus,
                    'total' => $total,
                    'done' => $done,
                    'rate' => $rate,
                    'hours' => round((float) ($worklogRows->get($emp->id)?->total_hours ?? 0), 1),
                    'overdue' => (int) ($overdueRows->get($emp->id)?->total ?? 0),
                ];
            });

        // ---- Weekly task completion (last 6 weeks) -----------------------
        $weeklyTrend = collect();
        $weekStart = now()->subWeeks(5)->startOfWeek();

        for ($i = 0; $i < 6; $i++) {
            $start = $weekStart->copy()->addWeeks($i);
            $end = $start->copy()->endOfWeek();

            $weeklyTrend->push([
                'label' => 'T'.$start->isoWeek(),
                'total' => Task::where('status', TaskStatus::Done)
                    ->whereBetween('updated_at', [$start, $end])
                    ->count(),
            ]);
        }

        // ---- Task status distribution per project for top 8 projects -----
        $projectTaskStats = Task::query()
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->select(
                'projects.id',
                'projects.name',
                'tasks.status',
                DB::raw('count(*) as total')
            )
            ->whereIn('projects.id', function ($q) {
                $q->select('id')
                    ->from('projects')
                    ->where('is_active', true)
                    ->limit(8);
            })
            ->groupBy('projects.id', 'projects.name', 'tasks.status')
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $first = $rows->first();
                $byStatus = [];
                foreach ($rows as $r) {
                    $byStatus[$r->status] = $r->total;
                }

                return [
                    'id' => $first->id,
                    'name' => $first->name,
                    'byStatus' => $byStatus,
                    'total' => array_sum($byStatus),
                ];
            })
            ->values();

        return Inertia::render('Dashboard/Team', [
            'members' => $members,
            'weeklyTrend' => $weeklyTrend,
            'projectTaskStats' => $projectTaskStats,
        ]);
    }
}
