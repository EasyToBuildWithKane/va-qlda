<?php

namespace App\Support\Performance;

use App\Models\Employee;
use App\Models\OrgTeamMember;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Collection;

/**
 * Tổng hợp chỉ số audit (cam kết vs kết quả) cho danh sách nhân sự trong một kỳ —
 * một lượt query task, không gọi EmployeeAuditBuilder từng người.
 */
class EmployeeAuditListBuilder
{
    public function __construct(private readonly PerformanceScorer $scorer) {}

    /**
     * @return array{employees: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function build(PerformanceFilter $filter): array
    {
        $employeeIds = $filter->employeeIds();
        if ($employeeIds->isEmpty()) {
            return [
                'employees' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $tasks = $this->periodTasks($filter, $employeeIds);
        $tasksByAssignee = $tasks->groupBy('assignee_id');

        $teamNames = $this->primaryTeamNames($employeeIds);

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path', 'role_title']);

        $rows = $employees->map(function (Employee $emp) use ($tasksByAssignee, $teamNames, $filter) {
            $metrics = $this->metricsForEmployee(
                $tasksByAssignee->get($emp->id, collect()),
                $filter,
            );

            return [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'role' => $emp->role_title,
                'avatar' => PublicMediaUrl::fromPublicDisk($emp->avatar_path),
                'teamName' => $teamNames[$emp->id] ?? '—',
                'periodLabel' => $filter->label,
                'committed' => $metrics['committed'],
                'done' => $metrics['done'],
                'commitmentRate' => $metrics['commitmentRate'],
                'avgScore' => $metrics['avgScore'],
                'grade' => $metrics['grade'],
            ];
        })->sortByDesc('avgScore')->values();

        $ranked = $rows->values()->map(function (array $row, int $index) {
            $row['rank'] = $index + 1;

            return $row;
        })->all();

        return [
            'employees' => $ranked,
            'summary' => $this->aggregateSummary(collect($ranked)),
        ];
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, Task>
     */
    private function periodTasks(PerformanceFilter $filter, Collection $employeeIds): Collection
    {
        $query = Task::query()
            ->whereIn('assignee_id', $employeeIds)
            ->whereNull('parent_id')
            ->where(function ($q) use ($filter) {
                $q->whereBetween('due_date', [$filter->start->toDateString(), $filter->end->toDateString()])
                    ->orWhereBetween('completed_at', [$filter->start, $filter->end])
                    ->orWhereBetween('work_started_at', [$filter->start, $filter->end]);
            });

        if ($filter->projectId) {
            $query->where('project_id', $filter->projectId);
        }
        if ($filter->sprintId) {
            $query->where('sprint_id', $filter->sprintId);
        }
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }

        return $query->get();
    }

    /**
     * Cam kết = task đến hạn hoặc bắt đầu làm trong kỳ (cùng semantics weekCard).
     *
     * @param  Collection<int, Task>  $allTasks
     * @return array{committed:int, done:int, commitmentRate:int, avgScore:int, grade:string}
     */
    private function metricsForEmployee(Collection $allTasks, PerformanceFilter $filter): array
    {
        $start = $filter->start;
        $end = $filter->end;

        $planned = $allTasks->filter(function (Task $t) use ($start, $end) {
            $due = $t->due_date && $t->due_date->betweenIncluded($start, $end);
            $started = $t->work_started_at && $t->work_started_at->betweenIncluded($start, $end);

            return $due || $started;
        });

        $committed = $planned->count();
        $done = $planned->filter(
            fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at && $t->completed_at->lte($end)
        )->count();

        $onTime = $planned->filter(
            fn (Task $t) => $t->status === TaskStatus::Done && $t->completed_at
                && $t->due_date && $t->completed_at->lte($t->due_date->copy()->endOfDay())
        )->count();

        $blocked = $planned->where('status', TaskStatus::Blocked)->count();

        $scores = $this->scorer->score([
            'assigned' => $committed,
            'done' => $done,
            'onTime' => $onTime,
            'overdue' => 0,
            'blocked' => $blocked,
        ]);

        $commitmentRate = $committed > 0 ? (int) round($done / $committed * 100) : 0;
        $avgScore = $committed > 0 ? $scores['performance'] : 0;

        return [
            'committed' => $committed,
            'done' => $done,
            'commitmentRate' => $commitmentRate,
            'avgScore' => $avgScore,
            'grade' => $committed > 0 ? $this->scorer->grade($avgScore) : '—',
        ];
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array<int, string>
     */
    private function primaryTeamNames(Collection $employeeIds): array
    {
        return OrgTeamMember::query()
            ->whereIn('employee_id', $employeeIds)
            ->with('team:id,name')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $members) => $members->first()?->team?->name ?? '—')
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function aggregateSummary(Collection $rows): array
    {
        $total = $rows->count();
        if ($total === 0) {
            return $this->emptySummary();
        }

        $withCommitment = $rows->filter(fn (array $r) => ($r['committed'] ?? 0) > 0);
        $avgCommitmentRate = $withCommitment->isNotEmpty()
            ? (int) round($withCommitment->avg('commitmentRate'))
            : 0;
        $avgScore = $withCommitment->isNotEmpty()
            ? (int) round($withCommitment->avg('avgScore'))
            : 0;

        $excellentCount = $rows->filter(fn (array $r) => in_array($r['grade'] ?? '', ['S', 'A'], true))->count();
        $needsImprovementCount = $rows->filter(fn (array $r) => in_array($r['grade'] ?? '', ['C', 'D'], true))->count();

        return [
            'total' => $total,
            'avgCommitmentRate' => $avgCommitmentRate,
            'avgScore' => $avgScore,
            'excellentCount' => $excellentCount,
            'needsImprovementCount' => $needsImprovementCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'avgCommitmentRate' => 0,
            'avgScore' => 0,
            'excellentCount' => 0,
            'needsImprovementCount' => 0,
        ];
    }
}
