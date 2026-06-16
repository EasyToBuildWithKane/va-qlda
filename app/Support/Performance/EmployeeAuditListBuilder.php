<?php

namespace App\Support\Performance;

use App\Models\Employee;
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

        $tasks = PerformanceTaskScope::forEmployees($employeeIds, $filter)->get();
        $tasksByAssignee = PerformanceTaskScope::groupByAssignee($tasks, $employeeIds);
        $bucketDefs = PerformancePeriodBuckets::forFilter($filter);

        $unitNames = EmployeeOrgUnitResolver::labelsFor($employeeIds);

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'avatar_path', 'role_title']);

        $rows = $employees->map(function (Employee $emp) use ($tasksByAssignee, $unitNames, $filter, $bucketDefs) {
            $empTasks = $tasksByAssignee->get($emp->id, collect());
            $metrics = $this->metricsForEmployee($empTasks, $filter);

            return [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'role' => $emp->role_title,
                'avatar' => PublicMediaUrl::fromPublicDisk($emp->avatar_path),
                'unitName' => $unitNames[$emp->id] ?? null,
                'periodLabel' => $filter->label,
                'periodBuckets' => $this->periodBucketsForEmployee($empTasks, $bucketDefs, $filter),
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
        return PerformanceTaskScope::forEmployees($employeeIds, $filter)->get();
    }

    /**
     * Cam kết = task đến hạn hoặc bắt đầu làm trong kỳ (cùng semantics weekCard).
     *
     * @param  Collection<int, Task>  $allTasks
     * @return array{committed:int, done:int, commitmentRate:int, avgScore:int, grade:string}
     */
    private function metricsForEmployee(
        Collection $allTasks,
        PerformanceFilter $filter,
        ?\Illuminate\Support\Carbon $start = null,
        ?\Illuminate\Support\Carbon $end = null,
    ): array {
        $start = $start ?? $filter->start;
        $end = $end ?? $filter->end;

        $planned = PerformanceTaskScope::plannedInBucket($allTasks, $start, $end, $filter);

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
            'grade' => $committed > 0 ? $this->scorer->grade($avgScore) : null,
        ];
    }

    /**
     * @param  Collection<int, Task>  $allTasks
     * @param  list<array{key:string, label:string, start:\Illuminate\Support\Carbon, end:\Illuminate\Support\Carbon}>  $bucketDefs
     * @return list<array<string, mixed>>
     */
    private function periodBucketsForEmployee(Collection $allTasks, array $bucketDefs, PerformanceFilter $filter): array
    {
        return collect($bucketDefs)->map(function (array $b) use ($allTasks, $filter) {
            $metrics = $this->metricsForEmployee($allTasks, $filter, $b['start'], $b['end']);
            $committed = $metrics['committed'];

            return [
                'key' => $b['key'],
                'label' => $b['label'],
                'range' => PerformanceDisplay::rangeLabel($b['start'], $b['end']),
                'committed' => $committed,
                'done' => $metrics['done'],
                'commitmentRate' => $metrics['commitmentRate'],
                'grade' => $committed > 0 ? $metrics['grade'] : null,
            ];
        })->all();
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
