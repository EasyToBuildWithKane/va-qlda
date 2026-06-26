<?php

namespace App\Application\Work;

use App\Domain\DailyReport\Models\DailyReport;
use App\Http\Resources\MyWorkTaskResource;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Models\Worklog;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskSource;
use App\Support\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Truy vấn tổng hợp cho màn hình "Việc của tôi" — task của một nhân viên trên
 * MỌI dự án, nhóm thành bucket theo thời gian, kèm summary KPI.
 *
 * Read-aggregation thuần: không mutate. Mọi thao tác (đổi status, log giờ) đi
 * qua endpoint project-scoped đã có (projects.tasks.status / projects.worklogs.store).
 *
 * Predicate gán việc (assignee_id HOẶC pivot task_assignees) tái dùng nguyên tắc
 * của App\Support\Performance\PerformanceTaskScope (không kèm ràng buộc parent_id
 * / cửa sổ ngày của module Hiệu suất, vì đây là "toàn bộ việc đang mở").
 */
class MyWorkQuery
{
    /**
     * Dữ liệu đầy đủ cho trang /my-work (self hoặc xem 1 thành viên).
     *
     * @param  array<string, mixed>  $filters
     * @return array{buckets: array<string, array<int, mixed>>, summary: array<string, mixed>, dailyReportToday: array<string, mixed>|null}
     */
    public function execute(SystemAccount $viewer, int $targetEmployeeId, array $filters, bool $canActTeam): array
    {
        $today = Carbon::today();
        $reportTaskIds = $this->todayReportTaskIds($targetEmployeeId, $today);

        $tasks = $this->listQuery($targetEmployeeId, $filters, $today)->get();
        $tasks = $this->mergeTodayReportTasks($tasks, $reportTaskIds, $targetEmployeeId, $filters, $today);
        $this->decorateCan($viewer, $tasks, $canActTeam);

        return [
            'buckets' => $this->bucketize($tasks, $today, $reportTaskIds),
            'summary' => $this->summaryFor($targetEmployeeId, $today),
            'dailyReportToday' => $this->dailyReportTodaySummary($targetEmployeeId, $today),
        ];
    }

    /**
     * Trạng thái báo cáo ngày hôm nay — luôn hiển thị trên /my-work (self/member).
     *
     * @return array<string, mixed>|null
     */
    public function dailyReportTodaySummary(int $employeeId, ?Carbon $today = null): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $today ??= Carbon::today();
        $dateStr = DailyReportCalendar::today();

        $report = DailyReport::query()
            ->forEmployee($employeeId)
            ->onDate($dateStr)
            ->first(['id', 'status', 'projects', 'is_late']);

        $reportTaskIds = $this->taskIdsFromReportProjects($report?->projects);
        $status = $report?->status;

        $needsAttention = $report === null
            || $status === ReportStatus::Draft;

        return [
            'date' => $dateStr,
            'hasReport' => $report !== null,
            'status' => $status?->value,
            'statusLabel' => $status?->label(),
            'statusColor' => $status?->color(),
            'href' => '/daily-reports/today',
            'reportTaskCount' => count($reportTaskIds),
            'isLate' => (bool) ($report?->is_late ?? false),
            'needsAttention' => $needsAttention,
        ];
    }

    /**
     * Dữ liệu gọn cho widget trên Dashboard Công Việc (self) — summary + vài việc
     * cần xử lý nhất (quá hạn + đến hạn hôm nay).
     *
     * @return array{summary: array<string, mixed>, tasks: array<int, mixed>}
     */
    public function widget(SystemAccount $viewer, int $employeeId, int $limit = 6): array
    {
        $today = Carbon::today();

        $tasks = $this->listQuery($employeeId, [], $today)
            ->get()
            ->filter(fn (Task $t) => $t->due_date !== null && $t->due_date->lte($today))
            ->sort($this->sorter())
            ->take($limit)
            ->values();

        $this->decorateCan($viewer, $tasks, false);

        return [
            'summary' => $this->summaryFor($employeeId, $today),
            'tasks' => MyWorkTaskResource::collection($tasks)->resolve(),
        ];
    }

    /**
     * KPI ổn định (không phụ thuộc bộ lọc danh sách) cho strip tóm tắt.
     *
     * @return array{open:int, overdue:int, dueToday:int, inProgress:int, hoursLoggedToday:float}
     */
    public function summaryFor(int $employeeId, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $rows = $this->assigneeScope(Task::query()->select('id', 'status', 'due_date'), $employeeId)
            ->where('status', '!=', TaskStatus::Done->value)
            ->get();

        $hoursToday = (float) Worklog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today->toDateString())
            ->sum('hours');

        return [
            'open' => $rows->count(),
            'overdue' => $rows->filter(fn (Task $t) => $t->due_date !== null && $t->due_date->lt($today))->count(),
            'dueToday' => $rows->filter(fn (Task $t) => $t->due_date !== null && $t->due_date->isSameDay($today))->count(),
            'inProgress' => $rows->filter(fn (Task $t) => $t->status === TaskStatus::InProgress)->count(),
            'hoursLoggedToday' => round($hoursToday, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    private function listQuery(int $employeeId, array $filters, Carbon $today): Builder
    {
        $query = $this->assigneeScope(Task::query(), $employeeId)
            ->with([
                'project:id,name,code,color',
                'sprint:id,name',
                'assignee:id,full_name,avatar_path',
                'worklogs' => fn ($w) => $w
                    ->whereDate('date', $today->toDateString())
                    ->where('employee_id', $employeeId),
            ]);

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, TaskStatus::values(), true)) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', TaskStatus::Done->value);
        }

        $priority = $filters['priority'] ?? null;
        if (is_string($priority) && in_array($priority, TaskPriority::values(), true)) {
            $query->where('priority', $priority);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        $term = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($term !== '') {
            $query->where('title', 'like', '%'.$term.'%');
        }

        return $query;
    }

    /**
     * Task id được tag trong báo cáo ngày hôm nay (kể cả chưa gán assignee trên task).
     *
     * @return array<int, int>
     */
    private function todayReportTaskIds(int $employeeId, Carbon $today): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $report = DailyReport::query()
            ->forEmployee($employeeId)
            ->onDate(DailyReportCalendar::today())
            ->first(['projects']);

        return $this->taskIdsFromReportProjects($report?->projects);
    }

    /**
     * @return array<int, int>
     */
    private function taskIdsFromReportProjects(mixed $projects): array
    {
        if (! is_array($projects)) {
            return [];
        }

        $ids = [];
        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }
            foreach ($project['tasks'] ?? [] as $taskRef) {
                if (! is_array($taskRef)) {
                    continue;
                }
                $id = (int) ($taskRef['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * Bổ sung task có trong báo cáo hôm nay nhưng không nằm trong assignee scope (chưa gán).
     *
     * @param  array<int, int>  $reportTaskIds
     * @param  array<string, mixed>  $filters
     */
    private function mergeTodayReportTasks(
        Collection $tasks,
        array $reportTaskIds,
        int $employeeId,
        array $filters,
        Carbon $today,
    ): Collection {
        if ($reportTaskIds === []) {
            return $tasks;
        }

        $existing = $tasks->pluck('id')->flip();
        $missing = array_values(array_filter($reportTaskIds, fn (int $id) => ! $existing->has($id)));
        if ($missing === []) {
            return $tasks;
        }

        $extraQuery = Task::query()
            ->whereIn('id', $missing)
            ->with([
                'project:id,name,code,color',
                'sprint:id,name',
                'assignee:id,full_name,avatar_path',
                'worklogs' => fn ($w) => $w
                    ->whereDate('date', $today->toDateString())
                    ->where('employee_id', $employeeId),
            ]);

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, TaskStatus::values(), true)) {
            $extraQuery->where('status', $status);
        } else {
            $extraQuery->where('status', '!=', TaskStatus::Done->value);
        }

        if (! empty($filters['project_id'])) {
            $extraQuery->where('project_id', (int) $filters['project_id']);
        }

        $term = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($term !== '') {
            $extraQuery->where('title', 'like', '%'.$term.'%');
        }

        return $tasks->concat($extraQuery->get())->unique('id')->values();
    }

    /**
     * Predicate "việc được giao cho $employeeId" — assignee chính HOẶC pivot.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function assigneeScope(Builder $query, int $employeeId): Builder
    {
        return $query->where(function (Builder $q) use ($employeeId) {
            $q->where('assignee_id', $employeeId)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('employees.id', $employeeId));
        });
    }

    /**
     * Tính cờ quyền thao tác cho TỪNG task — batch theo project để tránh N+1.
     * `can_contribute` = quyền project hiện có của người xem; `can_act_team` =
     * quyền oversight đã xác định ở controller.
     *
     * @param  Collection<int, Task>  $tasks
     */
    private function decorateCan(SystemAccount $viewer, Collection $tasks, bool $canActTeam): void
    {
        if ($tasks->isEmpty()) {
            return;
        }

        $projectIds = $tasks->pluck('project_id')->unique()->values();

        // ProjectPolicy::contribute = project.contribute | project.manage | manager | member.
        $globalContribute = $viewer->allows('project.contribute') || $viewer->allows('project.manage');

        $contributeProjectIds = collect();
        if (! $globalContribute && $viewer->employee_id !== null) {
            $managerProjectIds = Project::query()
                ->whereIn('id', $projectIds)
                ->where('manager_id', $viewer->employee_id)
                ->pluck('id');

            $memberProjectIds = DB::table('project_member')
                ->whereIn('project_id', $projectIds)
                ->where('employee_id', $viewer->employee_id)
                ->pluck('project_id');

            $contributeProjectIds = $managerProjectIds->merge($memberProjectIds)
                ->map(fn ($id) => (int) $id)
                ->unique();
        }

        foreach ($tasks as $task) {
            $canContribute = $globalContribute || $contributeProjectIds->contains((int) $task->project_id);
            $task->setAttribute('can_contribute', $canContribute);
            $task->setAttribute('can_act_team', $canActTeam);
        }
    }

    /**
     * Phân bucket theo due_date so với hôm nay; mỗi bucket sắp xếp theo độ ưu
     * tiên → hạn → id. Bao phủ TOÀN BỘ việc mở (upcoming = mọi việc tương lai).
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, array<int, mixed>>
     */
    /**
     * @param  array<int, int>  $todayReportTaskIds
     */
    private function bucketize(Collection $tasks, Carbon $today, array $todayReportTaskIds = []): array
    {
        $reportIdSet = array_fill_keys($todayReportTaskIds, true);
        $groups = ['overdue' => [], 'today' => [], 'upcoming' => [], 'no_due' => []];

        foreach ($tasks as $task) {
            $due = $task->due_date;

            if ($due === null) {
                if ($task->source === TaskSource::Daily || isset($reportIdSet[$task->id])) {
                    $groups['today'][] = $task;
                } else {
                    $groups['no_due'][] = $task;
                }
            } elseif ($due->lt($today) && $task->status !== TaskStatus::Done) {
                $groups['overdue'][] = $task;
            } elseif ($due->isSameDay($today)) {
                $groups['today'][] = $task;
            } else {
                $groups['upcoming'][] = $task;
            }
        }

        $out = [];
        foreach ($groups as $key => $list) {
            $collection = collect($list)->sort($this->sorter())->values();
            $out[$key] = MyWorkTaskResource::collection($collection)->resolve();
        }

        return $out;
    }

    /**
     * Sắp xếp: độ ưu tiên (weight) giảm → hạn tăng → id.
     */
    private function sorter(): callable
    {
        return function (Task $a, Task $b): int {
            $wa = $a->priority->weight();
            $wb = $b->priority->weight();
            if ($wa !== $wb) {
                return $wb <=> $wa;
            }

            $da = $a->due_date?->getTimestamp() ?? PHP_INT_MAX;
            $db = $b->due_date?->getTimestamp() ?? PHP_INT_MAX;
            if ($da !== $db) {
                return $da <=> $db;
            }

            return $a->id <=> $b->id;
        };
    }
}
