<?php

namespace App\Support\WeeklyReport;

use App\Models\Task;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\TaskStatus;

/**
 * Đánh giá rủi ro kỳ báo cáo → danh sách rủi ro phân mức High / Medium / Low kèm nguyên nhân.
 */
class WeeklyReportRiskAssessor
{
    /**
     * @return array{risks: array<int, array{level:string,label:string,reason:string}>, summary: array{high:int,medium:int,low:int}}
     */
    public function assess(WeeklyReportContext $context, array $kpi): array
    {
        $risks = [];

        foreach ($context->blockers as $blocker) {
            $level = match ($blocker->severity) {
                BlockerSeverity::Critical, BlockerSeverity::High => 'high',
                BlockerSeverity::Medium => 'medium',
                default => 'low',
            };
            $sev = $blocker->severity?->label() ?? 'Chưa rõ';
            $taskBit = $blocker->task?->title ? ' · gắn «'.$blocker->task->title.'»' : '';
            $ownerBit = $blocker->owner?->full_name
                ? 'Phụ trách: '.$blocker->owner->full_name
                : 'Chưa có người phụ trách';
            $risks[] = [
                'level' => $level,
                'label' => 'Vướng mắc ['.$sev.']: '.$blocker->title.$taskBit,
                'reason' => $ownerBit.'.',
            ];
        }

        $overdueTasks = $context->tasks
            ->filter(fn (Task $t) => $t->due_date !== null
                && $t->due_date->isPast()
                && $t->status !== TaskStatus::Done)
            ->sortBy('due_date');
        $overdue = $overdueTasks->count();
        if ($overdue > 0) {
            $sample = $overdueTasks->take(3)->map(function (Task $t) use ($context) {
                $who = implode(', ', WeeklyReportTaskFacts::memberNames($t, $context)) ?: 'Chưa gán';

                return $t->title.' ('.$who.', hạn '.$t->due_date->format('d/m').')';
            })->implode('; ');
            $risks[] = [
                'level' => $overdue >= 3 ? 'high' : 'medium',
                'label' => "Có {$overdue} công việc quá hạn",
                'reason' => $sample.'. Cần điều phối lại nguồn lực hoặc dời mốc.',
            ];
        }

        $blockedTasks = $context->tasks->filter(fn (Task $t) => $t->status === TaskStatus::Blocked);
        $blocked = $blockedTasks->count();
        if ($blocked > 0) {
            $sample = $blockedTasks
                ->sortByDesc(fn (Task $t) => $t->priority->weight())
                ->take(3)
                ->pluck('title')
                ->implode('; ');
            $risks[] = [
                'level' => 'medium',
                'label' => "{$blocked} công việc đang bị chặn",
                'reason' => $sample.'. Đang chờ tháo gỡ phụ thuộc trước khi tiếp tục.',
            ];
        }

        $progress = (int) ($kpi['sprint_progress'] ?? 0);
        $daysLeft = $context->weekEnd->isFuture()
            ? now()->diffInDays($context->weekEnd, false)
            : 0;
        if ($progress < 50 && $daysLeft <= 3 && $context->tasks->isNotEmpty()) {
            $risks[] = [
                'level' => 'high',
                'label' => 'Tiến độ kỳ thấp so với thời gian còn lại',
                'reason' => "Mới đạt {$progress}% trong khi sắp hết tuần.",
            ];
        }

        return [
            'risks' => $risks,
            'summary' => [
                'high' => $this->countLevel($risks, 'high'),
                'medium' => $this->countLevel($risks, 'medium'),
                'low' => $this->countLevel($risks, 'low'),
            ],
        ];
    }

    private function countLevel(array $risks, string $level): int
    {
        return count(array_filter($risks, fn ($r) => $r['level'] === $level));
    }
}
