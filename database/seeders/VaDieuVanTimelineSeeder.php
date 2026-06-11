<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use App\Support\TaskCompletion;
use App\Support\TaskProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nạp dự án «Phần mềm Điều vận» từ file Excel Timeline Process Plan.
 *
 * Cập nhật JSON: node scripts/export-va-timeline-seed.mjs
 *
 * Chạy: php artisan db:seed --class=VaDieuVanTimelineSeeder
 */
class VaDieuVanTimelineSeeder extends Seeder
{
    private const PM_EMAIL = 'kieunlt@hcm.vaschools.edu.vn';

    private const DEV_EMAIL = 'khoana@hcm.vaschools.edu.vn';

    public function run(): void
    {
        $path = database_path('seeders/data/va_dieuvan_timeline.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Thiếu file dữ liệu: database/seeders/data/va_dieuvan_timeline.json — chạy node scripts/export-va-timeline-seed.mjs');
        }

        /** @var array{project: array<string, mixed>, sprints: array<int, array<string, mixed>>, tasks: array<int, array<string, mixed>>} $data */
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $pm = Employee::where('email', self::PM_EMAIL)->first();
        $dev = Employee::where('email', self::DEV_EMAIL)->first();
        if (! $pm || ! $dev) {
            throw new RuntimeException(
                'Cần có nhân sự trong DB: '.self::PM_EMAIL.' (chủ dự án) và '.self::DEV_EMAIL.' (thực hiện).',
            );
        }

        DB::transaction(function () use ($data, $pm, $dev) {
            $p = $data['project'];
            $project = Project::updateOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                    'color' => 'brand',
                    'status' => ProjectStatus::Active->value,
                    'type' => ProjectType::Deployment->value,
                    'start_date' => $p['start_date'] ?? null,
                    'due_date' => $p['due_date'] ?? null,
                    'manager_id' => $pm->id,
                    'is_active' => true,
                    'sort_order' => 50,
                ],
            );

            $this->syncMembers($project, $pm, $dev);

            Task::where('project_id', $project->id)->forceDelete();
            Sprint::where('project_id', $project->id)->delete();

            $sprintIds = [];
            foreach ($data['sprints'] as $row) {
                $num = (int) ($row['num'] ?? 0);
                $sprint = Sprint::create([
                    'project_id' => $project->id,
                    'name' => $row['name'] ?? "Sprint {$num}",
                    'goal' => $row['goal'] ?? null,
                    'status' => $this->mapSprintStatus($row['status'] ?? ''),
                    'start_date' => $row['start'] ?? null,
                    'end_date' => $row['end'] ?? null,
                    'sort_order' => $num,
                ]);
                $sprintIds[$num] = $sprint->id;
            }

            $taskIdsByWbs = [];
            $order = 0;

            foreach ($data['tasks'] as $row) {
                $wbs = (string) ($row['wbs'] ?? '');
                if ($wbs === '') {
                    continue;
                }

                $sprintNum = (int) ($row['sprintNum'] ?? 0);
                if ($sprintNum === 0) {
                    $sprintNum = (int) explode('.', $wbs)[0];
                }

                $status = $this->mapTaskStatus($row['status'] ?? '');
                $assignee = $this->resolveAssignee($row['owner'] ?? '', $pm, $dev);
                $parentWbs = $this->parentWbs($wbs);
                $parentId = $parentWbs !== null ? ($taskIdsByWbs[$parentWbs] ?? null) : null;

                $estimate = isset($row['estimate_hours']) && is_numeric($row['estimate_hours'])
                    ? (float) $row['estimate_hours']
                    : $this->defaultEstimate($row);

                $progress = $this->mapProgress($row, $status);
                [$title, $description] = $this->resolveTitleAndDescription($row, $wbs);

                $payload = [
                    'project_id' => $project->id,
                    'sprint_id' => $sprintIds[$sprintNum] ?? null,
                    'parent_id' => $parentId,
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'priority' => TaskPriority::Medium->value,
                    'assignee_id' => $assignee->id,
                    'reporter_id' => $pm->id,
                    'start_date' => $row['start'] ?? null,
                    'due_date' => $row['end'] ?? null,
                    'estimate_hours' => $estimate > 0 ? $estimate : null,
                    'progress' => $progress,
                    'order_column' => ++$order,
                ];

                if ($status === TaskStatus::Done->value) {
                    $actual = isset($row['actual_hours']) && is_numeric($row['actual_hours']) && (float) $row['actual_hours'] > 0
                        ? (float) $row['actual_hours']
                        : ($estimate > 0 ? $estimate : 1.0);
                    $completedAt = isset($row['end']) ? Carbon::parse($row['end'])->endOfDay() : now();
                    $payload = array_merge($payload, TaskCompletion::completionAttributes(
                        new Task(array_merge($payload, ['work_started_at' => $payload['start_date'] ? Carbon::parse($payload['start_date'])->setTime(9, 0) : null])),
                        $actual,
                        $row['note'] ?? null,
                    ));
                    $payload['completed_at'] = $completedAt;
                } elseif ($status === TaskStatus::InProgress->value) {
                    $payload['work_started_at'] = isset($row['start'])
                        ? Carbon::parse($row['start'])->setTime(9, 0)
                        : now();
                }

                $task = Task::create($payload);
                $taskIdsByWbs[$wbs] = $task->id;
            }
        });

        $this->command?->info('Đã nạp dự án VA-DV (Điều vận): sprints + tasks từ Timeline Process Plan.');
    }

    private function syncMembers(Project $project, Employee $pm, Employee $dev): void
    {
        $project->members()->syncWithoutDetaching([
            $pm->id => [
                'role' => 'pm',
                'rate_type' => 'monthly',
                'rate' => 0,
                'allocation' => 30,
                'joined_at' => now()->subMonths(3)->toDateString(),
                'is_active' => true,
            ],
            $dev->id => [
                'role' => 'developer',
                'rate_type' => 'hourly',
                'rate' => 0,
                'allocation' => 100,
                'joined_at' => now()->subMonths(3)->toDateString(),
                'is_active' => true,
            ],
        ]);
    }

    private function mapSprintStatus(string $raw): string
    {
        $n = mb_strtolower(trim($raw));

        return match (true) {
            str_contains($n, 'hoàn') => SprintStatus::Completed->value,
            str_contains($n, 'đang') => SprintStatus::Active->value,
            default => SprintStatus::Planned->value,
        };
    }

    private function mapTaskStatus(string $raw): string
    {
        $n = mb_strtolower(trim($raw));

        return match (true) {
            str_contains($n, 'hoàn') => TaskStatus::Done->value,
            str_contains($n, 'đang') => TaskStatus::InProgress->value,
            str_contains($n, 'review') => TaskStatus::InReview->value,
            str_contains($n, 'chặn'), str_contains($n, 'block') => TaskStatus::Blocked->value,
            default => TaskStatus::Todo->value,
        };
    }

    private function mapProgress(array $row, string $status): int
    {
        if ($status === TaskStatus::Done->value) {
            return 100;
        }

        if (isset($row['progress']) && is_numeric($row['progress'])) {
            $p = (float) $row['progress'];
            if ($p > 0 && $p <= 1) {
                return (int) round($p * 100);
            }
            if ($p > 1 && $p <= 100) {
                return (int) round($p);
            }
        }

        return TaskProgress::fromStatus($status);
    }

    private function resolveAssignee(string $owner, Employee $pm, Employee $dev): Employee
    {
        $o = mb_strtolower(trim($owner));
        if ($o === '' || str_contains($o, 'kiều') || str_contains($o, 'kieu')) {
            return $pm;
        }
        if (str_contains($o, 'khoa')) {
            return $dev;
        }

        return $dev;
    }

    private function parentWbs(string $wbs): ?string
    {
        if (! str_contains($wbs, '.')) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '', $wbs) ?: null;
    }

    private function defaultEstimate(array $row): float
    {
        $start = $row['start'] ?? null;
        $end = $row['end'] ?? null;
        if ($start && $end) {
            $days = Carbon::parse($start)->diffInWeekdays(Carbon::parse($end)) + 1;

            return max(1, min(40, $days * 4));
        }

        return 4;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function resolveTitleAndDescription(array $row, string $wbs): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', (string) ($row['title'] ?? '')) ?: "Task {$wbs}");
        $title = mb_strlen($full) > 255 ? mb_substr($full, 0, 252).'...' : $full;

        $parts = [];
        if ($full !== $title) {
            $parts[] = $full;
        }
        if (! empty($row['link'])) {
            $parts[] = 'Liên kết: '.$row['link'];
        }
        if (! empty($row['note'])) {
            $parts[] = 'Ghi chú: '.$row['note'];
        }
        $parts[] = 'WBS: '.$wbs;

        return [$title, implode("\n\n", $parts)];
    }
}
