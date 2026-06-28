<?php

namespace App\Support\WeeklyReport;

use App\Models\Task;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\TaskStatus;

/**
 * Đánh giá rủi ro Sprint → danh sách rủi ro phân mức High / Medium / Low kèm nguyên nhân.
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
            $risks[] = [
                'level' => $level,
                'label' => 'Vướng mắc: '.$blocker->title,
                'reason' => $blocker->owner?->full_name
                    ? 'Phụ trách: '.$blocker->owner->full_name
                    : 'Chưa có người phụ trách.',
            ];
        }

        $overdue = (int) ($kpi['overdue'] ?? 0);
        if ($overdue > 0) {
            $risks[] = [
                'level' => $overdue >= 3 ? 'high' : 'medium',
                'label' => "Có {$overdue} công việc quá hạn",
                'reason' => 'Cần điều phối lại nguồn lực hoặc dời mốc thời gian.',
            ];
        }

        $blocked = $context->tasks->filter(fn (Task $t) => $t->status === TaskStatus::Blocked)->count();
        if ($blocked > 0) {
            $risks[] = [
                'level' => 'medium',
                'label' => "{$blocked} công việc đang bị chặn",
                'reason' => 'Đang chờ tháo gỡ phụ thuộc trước khi tiếp tục.',
            ];
        }

        $progress = (int) ($kpi['sprint_progress'] ?? 0);
        $daysLeft = $context->weekEnd->isFuture()
            ? now()->diffInDays($context->weekEnd, false)
            : 0;
        if ($progress < 50 && $daysLeft <= 3 && $context->tasks->isNotEmpty()) {
            $risks[] = [
                'level' => 'high',
                'label' => 'Tiến độ Sprint thấp so với thời gian còn lại',
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
