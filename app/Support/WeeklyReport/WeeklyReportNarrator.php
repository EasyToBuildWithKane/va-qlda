<?php

namespace App\Support\WeeklyReport;

use App\Models\Blocker;
use App\Models\Feedback;
use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Collection;

/**
 * Sinh văn bản báo cáo quản trị (tiếng Việt) từ dữ liệu Sprint.
 *
 * Nguyên tắc: mỗi dòng gắn thực thể cụ thể + meta (assignee, hạn, ưu tiên, ngày);
 * thẻ «Kết quả» chỉ lấy việc trong cửa sổ tuần — không đổ full Sprint.
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
                'risk' => $this->riskNarrative($context, $risk),
                'feedback' => $this->feedbackNarrative($context, $feedback),
                'activity' => $this->activityNarrative($context),
            ],
        ];
    }

    private function executive(WeeklyReportContext $context, array $kpi, array $risk): string
    {
        $progress = (int) $kpi['sprint_progress'];
        $done = (int) $kpi['completed_tasks'];
        $total = (int) $kpi['total_tasks'];
        $remaining = (int) $kpi['remaining_tasks'];
        $highRisks = (int) $risk['summary']['high'];
        $weekDone = $this->tasksCompletedInWeek($context)->count();
        $hours = (float) $kpi['worklog_hours'];
        $sprintName = $context->sprint?->name ?? 'ngoài Sprint';
        $weekLabel = $this->weekLabel($context);

        $pace = match (true) {
            $progress >= 85 => 'bám sát kế hoạch và gần hoàn tất',
            $progress >= 60 => 'tiến triển ổn định, đúng định hướng',
            $progress >= 40 => 'đang triển khai nhưng cần đẩy nhanh',
            default => 'mới ở giai đoạn đầu và cần tập trung nguồn lực',
        };

        $parts = [];
        $parts[] = "{$sprintName} — {$weekLabel}: đạt khoảng {$progress}% kế hoạch ({$done}/{$total} hạng mục, còn {$remaining}), {$pace}.";

        if ($weekDone > 0) {
            $parts[] = "Trong tuần hoàn thành {$weekDone} công việc.";
        } else {
            $parts[] = 'Trong tuần chưa ghi nhận hạng mục hoàn thành mới.';
        }

        if ($hours > 0) {
            $parts[] = "Công sức ghi nhận: {$hours} giờ.";
        }

        if ((int) $kpi['critical_bugs'] === 0 && (int) $kpi['blocked'] === 0) {
            $parts[] = 'Không có công việc bị chặn hay lỗi nghiêm trọng.';
        } else {
            if ((int) $kpi['blocked'] > 0) {
                $parts[] = "Đang có {$kpi['blocked']} công việc bị chặn.";
            }
            if ((int) $kpi['critical_bugs'] > 0) {
                $parts[] = "Còn {$kpi['critical_bugs']} lỗi nghiêm trọng cần ưu tiên.";
            }
        }

        if ((int) $kpi['overdue'] > 0) {
            $parts[] = "{$kpi['overdue']} hạng mục quá hạn cần xử lý ngay.";
        }

        if ($highRisks > 0) {
            $parts[] = "{$highRisks} rủi ro mức cao cần Ban lãnh đạo quan tâm trước khi Release.";
        } else {
            $parts[] = 'Chưa phát sinh rủi ro lớn ảnh hưởng tới mốc bàn giao.';
        }

        return implode(' ', $parts);
    }

    private function insight(WeeklyReportContext $context, array $kpi, array $risk, array $feedback): string
    {
        $signals = [];

        $overdueTasks = $this->overdueTasks($context)->take(2);
        if ($overdueTasks->isNotEmpty()) {
            $names = $overdueTasks->pluck('title')->implode(', ');
            $extra = (int) $kpi['overdue'] > 2 ? ' (và '.((int) $kpi['overdue'] - 2).' hạng mục khác)' : '';
            $signals[] = "quá hạn: {$names}{$extra}";
        }

        $blockedTasks = $context->tasks
            ->filter(fn (Task $t) => $t->status === TaskStatus::Blocked)
            ->sortByDesc(fn (Task $t) => $t->priority->weight())
            ->take(2);
        if ($blockedTasks->isNotEmpty()) {
            $signals[] = 'bị chặn: '.$blockedTasks->pluck('title')->implode(', ');
        }

        $topBlockers = $context->blockers->take(2);
        if ($topBlockers->isNotEmpty()) {
            $signals[] = 'vướng mắc: '.$topBlockers->pluck('title')->implode(', ');
        }

        $changeRequests = $this->feedbackCount($feedback, 'change_request');
        if ($changeRequests > 0) {
            $titles = $context->feedbacks
                ->filter(fn (Feedback $f) => (new WeeklyReportFeedbackClassifier)->bucketFor($f) === 'change_request')
                ->take(2)
                ->pluck('title')
                ->filter()
                ->values();
            $signals[] = $titles->isNotEmpty()
                ? "yêu cầu thay đổi ({$changeRequests}): ".$titles->implode(', ')
                : "{$changeRequests} yêu cầu thay đổi từ người dùng";
        }

        $highRisks = collect($risk['risks'])->where('level', 'high')->take(2);
        foreach ($highRisks as $item) {
            $signals[] = 'rủi ro cao: '.$item['label'];
        }

        if ($signals === []) {
            $weekDone = $this->tasksCompletedInWeek($context)->count();
            $hours = (float) $kpi['worklog_hours'];
            $bits = ["tiến độ Sprint {$kpi['sprint_progress']}%"];
            if ($weekDone > 0) {
                $bits[] = "hoàn thành {$weekDone} hạng mục trong tuần";
            }
            if ($hours > 0) {
                $bits[] = "{$hours} giờ công";
            }

            return 'Nhịp độ tuần tốt: '.$this->joinNatural($bits)
                .'. Không có tín hiệu cảnh báo nổi bật cần Ban lãnh đạo can thiệp.';
        }

        return 'Điểm cần chú ý nhất '.$this->weekLabel($context).': '.$this->joinNatural($signals).'. '
            .'Đề nghị ưu tiên tháo gỡ các hạng mục trên để bảo đảm cam kết Sprint.';
    }

    private function result(WeeklyReportContext $context, array $kpi): string
    {
        $lines = [];
        $completedInWeek = $this->tasksCompletedInWeek($context)
            ->sortByDesc(fn (Task $t) => $t->completed_at ?? $t->updated_at);

        foreach ($completedInWeek->take(10) as $task) {
            $prefix = $task->is_milestone ? 'Đạt mốc' : 'Hoàn thành';
            $at = $task->completed_at ?? $task->updated_at;
            $meta = $this->taskMeta($task, [
                $at ? 'ngày '.$at->format('d/m') : null,
            ]);
            $value = WeeklyReportTaskFacts::plain($task->completion_note, 140)
                ?: WeeklyReportTaskFacts::plain($task->description, 140);
            $lines[] = $value !== ''
                ? "{$prefix}: {$task->title}{$meta} — {$value}"
                : "{$prefix}: {$task->title}{$meta}.";
        }

        $hoursByTask = $context->worklogs
            ->groupBy('task_id')
            ->map(fn (Collection $rows) => round((float) $rows->sum(fn ($w) => (float) $w->hours), 1));

        $progressThisWeek = $context->tasks
            ->whereIn('id', $hoursByTask->keys()->all())
            ->filter(fn (Task $t) => $t->status !== TaskStatus::Done && ! $completedInWeek->pluck('id')->contains($t->id))
            ->sortByDesc(fn (Task $t) => $t->priority->weight());

        foreach ($progressThisWeek->take(5) as $task) {
            $h = $hoursByTask->get($task->id, 0);
            $meta = $this->taskMeta($task, [$h > 0 ? "{$h} giờ" : null]);
            $lines[] = "Có tiến độ ghi nhận trong tuần: {$task->title}{$meta}.";
        }

        $deployEvents = $context->activities
            ->filter(fn ($a) => $this->mentions($a->event.' '.$a->description, ['deploy', 'release', 'phát hành', 'triển khai']));
        foreach ($deployEvents->take(2) as $event) {
            $snippet = $this->truncate((string) ($event->description ?: $event->event), 80);
            $lines[] = "Triển khai/phát hành: {$snippet}.";
        }

        $hours = (float) $kpi['worklog_hours'];
        if ($hours > 0 && $lines !== []) {
            $people = $context->worklogs->pluck('employee_id')->filter()->unique()->count();
            $peopleBit = $people > 0 ? " / {$people} người" : '';
            $lines[] = "Tổng công sức tuần: {$hours} giờ{$peopleBit}.";
        }

        if ($completedInWeek->isNotEmpty()) {
            $lines[] = 'Tuần này hoàn thành '.$completedInWeek->count().' hạng mục (không tính việc done từ tuần trước).';
        }

        return $this->bullets($lines, 'Chưa có kết quả nổi bật được ghi nhận trong tuần.');
    }

    private function current(WeeklyReportContext $context, array $kpi, array $risk): string
    {
        $lines = [];

        $active = $context->tasks
            ->filter(fn (Task $t) => in_array($t->status, [
                TaskStatus::InProgress,
                TaskStatus::InReview,
                TaskStatus::Blocked,
            ], true))
            ->sortByDesc(fn (Task $t) => $t->priority->weight());

        foreach ($active->take(8) as $task) {
            $meta = $this->taskMeta($task, [
                $task->priority->label() !== 'Trung bình' ? 'ƯT '.$task->priority->label() : null,
                $task->due_date ? 'hạn '.$task->due_date->format('d/m') : null,
            ]);
            $lines[] = "{$task->status->label()}: {$task->title}{$meta}.";
        }

        foreach ($context->blockers->take(5) as $blocker) {
            $lines[] = $this->blockerLine($blocker);
        }

        foreach ($this->overdueTasks($context)->take(4) as $task) {
            $days = $task->due_date?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0;
            $meta = $this->taskMeta($task, [
                'quá '.max(1, (int) $days).' ngày',
                $task->due_date ? 'hạn '.$task->due_date->format('d/m') : null,
            ]);
            $lines[] = "Quá hạn: {$task->title}{$meta}.";
        }

        if ($lines === []) {
            $lines[] = "Sprint đạt khoảng {$kpi['sprint_progress']}% kế hoạch, velocity ~{$kpi['team_velocity']}%, chưa có hạng mục đang làm / bị chặn.";
            if ((int) $kpi['critical_bugs'] === 0) {
                $lines[] = 'Hệ thống ổn định, không ghi nhận lỗi nghiêm trọng.';
            }
        } else {
            $inProgress = $context->tasks->filter(fn (Task $t) => $t->status === TaskStatus::InProgress)->count();
            $inReview = $context->tasks->filter(fn (Task $t) => $t->status === TaskStatus::InReview)->count();
            $lines[] = "Tiến độ Sprint: {$kpi['sprint_progress']}% ({$kpi['completed_tasks']}/{$kpi['total_tasks']})"
                ." — đang làm {$inProgress}, review {$inReview}, bị chặn {$kpi['blocked']}, vướng mắc mở {$kpi['open_issues']}.";
        }

        if ((int) $risk['summary']['high'] > 0) {
            $labels = collect($risk['risks'])->where('level', 'high')->take(2)->pluck('label')->implode('; ');
            $lines[] = "Rủi ro cao cần theo dõi: {$labels}.";
        }

        return $this->bullets($lines, 'Tình hình Sprint ổn định, chưa có vấn đề cần lưu ý.');
    }

    private function next(WeeklyReportContext $context, array $feedback): string
    {
        $lines = [];

        $blockedFirst = $context->tasks
            ->filter(fn (Task $t) => $t->status === TaskStatus::Blocked && $t->parent_id === null)
            ->sortByDesc(fn (Task $t) => $t->priority->weight());

        foreach ($blockedFirst->take(3) as $task) {
            $meta = $this->taskMeta($task, ['ƯT '.$task->priority->label()]);
            $lines[] = "Ưu tiên tháo chặn: {$task->title}{$meta}.";
        }

        $remaining = $context->tasks
            ->filter(fn (Task $t) => $t->status !== TaskStatus::Done
                && $t->parent_id === null
                && $t->status !== TaskStatus::Blocked)
            ->sortByDesc(fn (Task $t) => $t->priority->weight());

        foreach ($remaining->take(8) as $task) {
            $verb = match ($task->status) {
                TaskStatus::InProgress, TaskStatus::InReview => 'Tiếp tục',
                TaskStatus::Todo => 'Bắt đầu',
                default => 'Tiếp tục',
            };
            $meta = $this->taskMeta($task, [
                'ƯT '.$task->priority->label(),
                $task->due_date ? 'hạn '.$task->due_date->format('d/m') : null,
            ]);
            $lines[] = "{$verb}: {$task->title}{$meta}.";
        }

        foreach ($context->blockers->take(3) as $blocker) {
            $owner = $blocker->owner?->full_name;
            $taskBit = $blocker->task?->title ? " (gắn «{$blocker->task->title}»)" : '';
            $ownerBit = $owner ? " — {$owner}" : ' — chưa gán phụ trách';
            $lines[] = "Xử lý vướng mắc: {$blocker->title}{$taskBit}{$ownerBit}.";
        }

        $changeRequestFeedbacks = $context->feedbacks
            ->filter(fn (Feedback $f) => (new WeeklyReportFeedbackClassifier)->bucketFor($f) === 'change_request')
            ->take(3);
        foreach ($changeRequestFeedbacks as $fb) {
            $lines[] = 'Xử lý yêu cầu thay đổi: '.$this->truncate((string) $fb->title, 90).'.';
        }

        $dueSoon = $context->tasks
            ->filter(fn (Task $t) => $t->status !== TaskStatus::Done
                && $t->due_date !== null
                && $t->due_date->between(now()->startOfDay(), now()->addDays(7)->endOfDay()))
            ->sortBy('due_date')
            ->take(3);
        foreach ($dueSoon as $task) {
            if ($remaining->pluck('id')->contains($task->id) || $blockedFirst->pluck('id')->contains($task->id)) {
                continue;
            }
            $lines[] = 'Sắp tới hạn: '.$task->title.' (hạn '.$task->due_date->format('d/m').').';
        }

        return $this->bullets(array_slice($lines, 0, 14), 'Chưa có hạng mục kế hoạch cho tuần tiếp theo.');
    }

    private function riskNarrative(WeeklyReportContext $context, array $risk): string
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

        $overdue = $this->overdueTasks($context)->take(3);
        if ($overdue->isNotEmpty() && ! collect($risk['risks'])->contains(fn ($r) => str_contains($r['label'], 'quá hạn'))) {
            foreach ($overdue as $task) {
                $assignee = $task->assignee?->full_name ?? 'Chưa gán';
                $lines[] = '[Cao] Quá hạn: '.$task->title.' — Phụ trách: '.$assignee
                    .($task->due_date ? ', hạn '.$task->due_date->format('d/m/Y') : '');
            }
        }

        $summary = $risk['summary'];
        if ($lines !== []) {
            $lines[] = "Tổng hợp: {$summary['high']} cao · {$summary['medium']} trung bình · {$summary['low']} thấp.";
        }

        return $this->bullets($lines, 'Không có rủi ro đáng kể trong tuần.');
    }

    /**
     * @param  array{breakdown: array<int, array{key:string,label:string,color:string,count:int}>, total:int}  $feedback
     */
    private function feedbackNarrative(WeeklyReportContext $context, array $feedback): string
    {
        if ((int) $feedback['total'] === 0) {
            return '• Chưa ghi nhận phản hồi nào trong tuần.';
        }

        $classifier = new WeeklyReportFeedbackClassifier;
        $lines = [];

        foreach ($feedback['breakdown'] as $bucket) {
            if ($bucket['count'] === 0) {
                continue;
            }
            $samples = $context->feedbacks
                ->filter(fn (Feedback $f) => $classifier->bucketFor($f) === $bucket['key'])
                ->take(3)
                ->map(fn (Feedback $f) => $this->truncate((string) $f->title, 70))
                ->filter()
                ->values();

            $sampleBit = $samples->isNotEmpty() ? ': '.$samples->implode('; ') : '';
            $lines[] = "{$bucket['label']} ({$bucket['count']}){$sampleBit}.";
        }

        $avgRating = $context->feedbacks->whereNotNull('rating')->avg('rating');
        if ($avgRating !== null) {
            $lines[] = 'Điểm đánh giá trung bình: '.round((float) $avgRating, 1).'/5.';
        }

        $lines[] = 'Tổng cộng '.$feedback['total'].' phản hồi trong phạm vi báo cáo.';

        return $this->bullets($lines, 'Chưa ghi nhận phản hồi nào trong tuần.');
    }

    private function activityNarrative(WeeklyReportContext $context): string
    {
        $lines = $context->activities
            ->take(10)
            ->map(function ($a) {
                $when = $a->created_at?->format('d/m H:i');
                $text = $this->truncate((string) ($a->description ?: $a->event), 100);
                if ($text === '') {
                    return null;
                }

                return $when ? "[{$when}] {$text}" : $text;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($lines !== []) {
            $lines[] = 'Ghi nhận '.$context->activities->count().' sự kiện hoạt động trong tuần (hiển thị tối đa 10).';
        }

        return $this->bullets($lines, 'Không có sự kiện nổi bật được ghi nhận.');
    }

    // ---- helpers --------------------------------------------------------

    /** @return Collection<int, Task> */
    private function tasksCompletedInWeek(WeeklyReportContext $context): Collection
    {
        return WeeklyReportTaskFacts::completedInWeek($context);
    }

    /** @return Collection<int, Task> */
    private function overdueTasks(WeeklyReportContext $context): Collection
    {
        return $context->tasks
            ->filter(fn (Task $t) => $t->due_date !== null
                && $t->due_date->isPast()
                && $t->status !== TaskStatus::Done)
            ->sortBy('due_date')
            ->values();
    }

    private function weekLabel(WeeklyReportContext $context): string
    {
        return $context->periodLabel();
    }

    /**
     * @param  array<int, string|null>  $extra
     */
    private function taskMeta(Task $task, array $extra = []): string
    {
        $bits = array_values(array_filter([
            $task->assignee?->full_name,
            $task->epic?->name ? 'Epic: '.$task->epic->name : null,
            $task->story_points !== null && (float) $task->story_points > 0
                ? rtrim(rtrim(number_format((float) $task->story_points, 1, '.', ''), '0'), '.').' SP'
                : null,
            ...$extra,
        ]));

        return $bits === [] ? '' : ' ('.implode(' · ', $bits).')';
    }

    private function blockerLine(Blocker $blocker): string
    {
        $sev = $blocker->severity?->label() ?? 'Chưa rõ';
        $taskTitle = $blocker->task?->title;
        $owner = $blocker->owner?->full_name;
        $bits = array_values(array_filter([
            "mức {$sev}",
            $taskTitle ? "task «{$taskTitle}»" : null,
            $owner ? "phụ trách {$owner}" : 'chưa gán phụ trách',
        ]));

        return 'Vướng mắc: '.$blocker->title.' ('.implode(' · ', $bits).')'.'.';
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
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
