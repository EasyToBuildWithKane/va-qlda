<?php

namespace App\Support\WeeklyReport;

use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Collection;

/**
 * Sinh văn bản báo cáo quản trị (tiếng Việt) từ dữ liệu Sprint.
 *
 * Nguyên tắc: tổng hợp, gom nhóm, loại trùng, ưu tiên milestone > blocker >
 * priority cao; KHÔNG copy nguyên văn tiêu đề Task/Issue/Feedback.
 */
class WeeklyReportNarrator
{
    /**
     * @param  array<string, mixed>  $kpi
     * @param  array{risks: array<int, array{level:string,label:string,reason:string}>, summary: array{high:int,medium:int,low:int}}  $risk
     * @param  array{breakdown: array<int, array{key:string,label:string,color:string,count:int}>, total:int}  $feedback
     * @return array{executive: string, insight: string, sections: array<string, string>}
     */
    public function narrate(WeeklyReportContext $context, array $kpi, array $risk, array $feedback): array
    {
        return [
            'executive' => $this->executive($context, $kpi, $risk),
            'insight' => $this->insight($context, $kpi, $risk, $feedback),
            'sections' => [
                'result' => $this->result($context, $kpi),
                'current' => $this->current($context, $kpi, $risk),
                'next' => $this->next($context, $feedback),
                'risk' => $this->riskNarrative($risk),
                'feedback' => $this->feedbackNarrative($feedback),
                'activity' => $this->activityNarrative($context),
            ],
        ];
    }

    private function executive(WeeklyReportContext $context, array $kpi, array $risk): string
    {
        $progress = (int) $kpi['sprint_progress'];
        $done = (int) $kpi['completed_tasks'];
        $total = (int) $kpi['total_tasks'];
        $highRisks = (int) $risk['summary']['high'];

        $pace = match (true) {
            $progress >= 85 => 'bám sát kế hoạch và gần hoàn tất',
            $progress >= 60 => 'tiến triển ổn định, đúng định hướng',
            $progress >= 40 => 'đang triển khai nhưng cần đẩy nhanh',
            default => 'mới ở giai đoạn đầu và cần tập trung nguồn lực',
        };

        $parts = [];
        $parts[] = "Sprint hiện đạt khoảng {$progress}% kế hoạch ({$done}/{$total} hạng mục), {$pace}.";

        if ((int) $kpi['critical_bugs'] === 0 && (int) $kpi['blocked'] === 0) {
            $parts[] = 'Hệ thống vận hành ổn định, không ghi nhận lỗi nghiêm trọng.';
        } elseif ((int) $kpi['critical_bugs'] > 0) {
            $parts[] = 'Còn tồn tại lỗi nghiêm trọng cần ưu tiên xử lý.';
        }

        if ($highRisks > 0) {
            $parts[] = "Vẫn còn {$highRisks} rủi ro mức cao cần Ban lãnh đạo quan tâm trước khi Release.";
        } else {
            $parts[] = 'Chưa phát sinh rủi ro lớn ảnh hưởng tới mốc bàn giao.';
        }

        return implode(' ', $parts);
    }

    private function insight(WeeklyReportContext $context, array $kpi, array $risk, array $feedback): string
    {
        $signals = [];

        if ((int) $kpi['overdue'] > 0) {
            $signals[] = "{$kpi['overdue']} công việc quá hạn đang kéo lùi tiến độ";
        }
        if ((int) $kpi['open_issues'] > 0) {
            $signals[] = "{$kpi['open_issues']} vướng mắc chưa được tháo gỡ";
        }
        $changeRequests = $this->feedbackCount($feedback, 'change_request');
        if ($changeRequests > 0) {
            $signals[] = "{$changeRequests} yêu cầu thay đổi từ phía người dùng";
        }

        if ($signals === []) {
            return 'Dữ liệu Sprint cho thấy nhịp độ tốt: tiến độ và chất lượng được kiểm soát, không có tín hiệu cảnh báo nổi bật trong tuần.';
        }

        return 'Điểm cần chú ý nhất tuần này: '.$this->joinNatural($signals).'. '
            .'Đề nghị ưu tiên xử lý các hạng mục trên để bảo đảm cam kết Sprint.';
    }

    private function result(WeeklyReportContext $context, array $kpi): string
    {
        $lines = [];

        $milestones = $context->tasks
            ->filter(fn (Task $t) => $t->is_milestone && $t->status === TaskStatus::Done);
        foreach ($this->topTitles($milestones, 3) as $title) {
            $lines[] = "Đạt mốc: {$title}.";
        }

        $doneCount = (int) $kpi['completed_tasks'];
        if ($doneCount > 0) {
            $lines[] = "Hoàn thành {$doneCount} hạng mục công việc trong Sprint.";
        }

        $epicGroups = $context->tasks
            ->filter(fn (Task $t) => $t->status === TaskStatus::Done && $t->epic)
            ->groupBy(fn (Task $t) => $t->epic->name);
        foreach ($epicGroups->take(3) as $epicName => $group) {
            $lines[] = "Tiến triển hạng mục \"{$epicName}\": {$group->count()} công việc hoàn tất.";
        }

        $deployEvents = $context->activities
            ->filter(fn ($a) => $this->mentions($a->event.' '.$a->description, ['deploy', 'release', 'phát hành', 'triển khai']));
        if ($deployEvents->isNotEmpty()) {
            $lines[] = 'Thực hiện triển khai/phát hành phiên bản trong tuần.';
        }

        $hours = (float) $kpi['worklog_hours'];
        if ($hours > 0) {
            $lines[] = "Tổng công sức ghi nhận: {$hours} giờ làm việc.";
        }

        return $this->bullets($lines, 'Chưa có kết quả nổi bật được ghi nhận trong tuần.');
    }

    private function current(WeeklyReportContext $context, array $kpi, array $risk): string
    {
        $lines = [];
        $lines[] = "Sprint đạt khoảng {$kpi['sprint_progress']}% kế hoạch, velocity ~{$kpi['team_velocity']}%.";

        if ((int) $kpi['blocked'] === 0 && (int) $kpi['critical_bugs'] === 0) {
            $lines[] = 'Hệ thống ổn định, không có công việc bị chặn hay lỗi nghiêm trọng.';
        }
        if ((int) $kpi['open_issues'] > 0) {
            $lines[] = "Còn {$kpi['open_issues']} vướng mắc đang theo dõi xử lý.";
        }
        if ((int) $kpi['critical_bugs'] > 0) {
            $lines[] = "Đang xử lý {$kpi['critical_bugs']} lỗi nghiêm trọng.";
        }
        if ((int) $kpi['overdue'] > 0) {
            $lines[] = "Có {$kpi['overdue']} công việc quá hạn cần điều phối lại.";
        }
        if ((int) $risk['summary']['high'] > 0) {
            $lines[] = 'Đang chờ xác nhận từ các bên liên quan ở một số hạng mục quan trọng.';
        }

        return $this->bullets($lines, 'Tình hình Sprint ổn định, chưa có vấn đề cần lưu ý.');
    }

    private function next(WeeklyReportContext $context, array $feedback): string
    {
        $lines = [];

        $remaining = $context->tasks
            ->filter(fn (Task $t) => $t->status !== TaskStatus::Done && $t->parent_id === null)
            ->sortByDesc(fn (Task $t) => $t->priority->weight());
        foreach ($this->topTitles($remaining, 4) as $title) {
            $lines[] = "Tiếp tục: {$title}.";
        }

        $changeRequests = $this->feedbackCount($feedback, 'change_request');
        if ($changeRequests > 0) {
            $lines[] = "Đưa {$changeRequests} yêu cầu thay đổi vào kế hoạch xử lý.";
        }

        if ($context->blockers->isNotEmpty()) {
            $lines[] = 'Ưu tiên tháo gỡ các vướng mắc còn tồn đọng.';
        }

        return $this->bullets($lines, 'Chưa có hạng mục kế hoạch cho tuần tiếp theo.');
    }

    private function riskNarrative(array $risk): string
    {
        $lines = [];
        foreach ($risk['risks'] as $item) {
            $level = match ($item['level']) {
                'high' => 'Cao',
                'medium' => 'Trung bình',
                default => 'Thấp',
            };
            $lines[] = "[{$level}] {$item['label']} — {$item['reason']}";
        }

        return $this->bullets($lines, 'Không có rủi ro đáng kể trong tuần.');
    }

    private function feedbackNarrative(array $feedback): string
    {
        if ((int) $feedback['total'] === 0) {
            return 'Chưa ghi nhận phản hồi nào trong tuần.';
        }

        $lines = [];
        foreach ($feedback['breakdown'] as $bucket) {
            if ($bucket['count'] > 0) {
                $lines[] = "{$bucket['label']}: {$bucket['count']} phản hồi.";
            }
        }

        return $this->bullets($lines, 'Chưa ghi nhận phản hồi nào trong tuần.');
    }

    private function activityNarrative(WeeklyReportContext $context): string
    {
        $lines = $context->activities
            ->take(6)
            ->map(fn ($a) => $a->description)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->bullets($lines, 'Không có sự kiện nổi bật được ghi nhận.');
    }

    // ---- helpers --------------------------------------------------------

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, string>
     */
    private function topTitles(Collection $tasks, int $limit): array
    {
        return $tasks
            ->pluck('title')
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    private function feedbackCount(array $feedback, string $key): int
    {
        foreach ($feedback['breakdown'] as $bucket) {
            if ($bucket['key'] === $key) {
                return (int) $bucket['count'];
            }
        }

        return 0;
    }

    private function mentions(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<int, string>  $items */
    private function bullets(array $items, string $empty): string
    {
        if ($items === []) {
            return '• '.$empty;
        }

        return implode("\n", array_map(fn ($i) => '• '.$i, $items));
    }

    /** @param  array<int, string>  $items */
    private function joinNatural(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }
        $last = array_pop($items);

        return implode(', ', $items).' và '.$last;
    }
}
