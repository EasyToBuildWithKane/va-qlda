<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // ---- KPI cards -------------------------------------------------
        $totalProjects = Project::count();
        $activeProjects = Project::where('status', ProjectStatus::Active)->count();
        $totalMembers = Employee::where('is_active', true)->count();
        $totalTasks = Task::count();
        $doneTasks = Task::where('status', TaskStatus::Done)->count();
        $openBlockers = Blocker::open()->count();
        $overdueTasks = Task::where('status', '!=', TaskStatus::Done)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();
        $openBugs = Bug::whereNotIn('status', ['closed', 'resolved', 'wontfix'])->count();

        // ---- Project status distribution --------------------------------
        $projectsByStatus = Project::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status->value,
                'label' => $r->status->label(),
                'color' => $r->status->color(),
                'total' => $r->total,
            ]);

        // ---- Task status distribution ------------------------------------
        $tasksByStatus = Task::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status->value,
                'label' => $r->status->label(),
                'color' => $r->status->color(),
                'total' => $r->total,
            ]);

        // ---- Blocker severity distribution (open only) -------------------
        $blockersBySeverity = Blocker::open()
            ->select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->get()
            ->map(fn ($r) => [
                'severity' => $r->severity->value,
                'label' => $r->severity->label(),
                'color' => $r->severity->color(),
                'total' => $r->total,
            ]);

        // ---- Task completion trend (last 30 days) ------------------------
        $completionTrend = Task::where('status', TaskStatus::Done)
            ->whereDate('updated_at', '>=', now()->subDays(29))
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trendDays = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $trendDays->push([
                'date' => $date,
                'label' => now()->subDays($i)->format('d/m'),
                'total' => $completionTrend->get($date)?->total ?? 0,
            ]);
        }

        // ---- Top projects by progress ------------------------------------
        $topProjects = Project::with(['tasks:id,project_id,progress'])
            ->withCount('tasks')
            ->active()
            ->orderBy('name')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'color' => $p->color,
                'status' => $p->status->value,
                'progress' => $p->progress(),
                'tasks' => $p->tasks_count,
            ])
            ->sortByDesc('progress')
            ->values();

        // ---- Task priority distribution ---------------------------------
        $tasksByPriority = Task::select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->get()
            ->map(fn ($r) => [
                'priority' => $r->priority->value,
                'label' => $r->priority->label(),
                'color' => $r->priority->color(),
                'total' => $r->total,
            ]);

        return Inertia::render('Dashboard/Index', [
            'kpi' => [
                'totalProjects' => $totalProjects,
                'activeProjects' => $activeProjects,
                'totalMembers' => $totalMembers,
                'totalTasks' => $totalTasks,
                'doneTasks' => $doneTasks,
                'openBlockers' => $openBlockers,
                'overdueTasks' => $overdueTasks,
                'openBugs' => $openBugs,
            ],
            'projectsByStatus' => $projectsByStatus,
            'tasksByStatus' => $tasksByStatus,
            'blockersBySeverity' => $blockersBySeverity,
            'completionTrend' => $trendDays,
            'topProjects' => $topProjects,
            'tasksByPriority' => $tasksByPriority,
        ]);
    }
}
