<?php

namespace App\Support\WeeklyReport;

use App\Models\Task;
use App\Support\Enums\TaskStatus;
use Illuminate\Support\Collection;

/**
 * Trích nội dung task (mô tả, ghi chú hoàn thành) + việc done trong cửa sổ tuần
 * để KPI và LLM tổng hợp giá trị giao được — không chỉ đếm số.
 */
final class WeeklyReportTaskFacts
{
    /** @return Collection<int, Task> */
    public static function completedInWeek(WeeklyReportContext $context): Collection
    {
        $start = $context->weekStart->copy()->startOfDay();
        $end = $context->weekEnd->copy()->endOfDay();

        return $context->tasks
            ->filter(function (Task $task) use ($start, $end) {
                if ($task->status !== TaskStatus::Done) {
                    return false;
                }

                $at = $task->completed_at ?? $task->updated_at;

                return $at !== null && $at->between($start, $end);
            })
            ->sortByDesc(fn (Task $t) => $t->completed_at ?? $t->updated_at)
            ->values();
    }

    /**
     * Gói task cho LLM: tiêu đề + mô tả + ghi chú hoàn thành (đã cắt HTML).
     *
     * @return array{
     *     completed_this_week: array<int, array<string, mixed>>,
     *     in_progress: array<int, array<string, mixed>>,
     *     blocked: array<int, array<string, mixed>>
     * }
     */
    public static function digest(WeeklyReportContext $context, int $limit = 18): array
    {
        $completedIds = self::completedInWeek($context)->pluck('id');

        return [
            'completed_this_week' => self::completedInWeek($context)
                ->take($limit)
                ->map(fn (Task $t) => self::row($t, rich: true))
                ->values()
                ->all(),
            'in_progress' => $context->tasks
                ->filter(fn (Task $t) => in_array($t->status, [TaskStatus::InProgress, TaskStatus::InReview], true))
                ->sortByDesc(fn (Task $t) => $t->priority->weight())
                ->take(12)
                ->map(fn (Task $t) => self::row($t, rich: true))
                ->values()
                ->all(),
            'blocked' => $context->tasks
                ->filter(fn (Task $t) => $t->status === TaskStatus::Blocked)
                ->sortByDesc(fn (Task $t) => $t->priority->weight())
                ->take(8)
                ->map(fn (Task $t) => self::row($t, rich: false))
                ->values()
                ->all(),
            'other_done_outside_week' => $context->tasks
                ->filter(fn (Task $t) => $t->status === TaskStatus::Done && ! $completedIds->contains($t->id))
                ->count(),
        ];
    }

    /**
     * Fallback khi chưa có LLM: giá trị = ghi chú hoàn thành hoặc mô tả rút gọn.
     *
     * @return array<int, array{title: string, value: string, story_points: float|null}>
     */
    public static function heuristicOutcomes(WeeklyReportContext $context): array
    {
        return self::completedInWeek($context)
            ->take(12)
            ->map(function (Task $task) {
                $value = self::plain($task->completion_note, 220)
                    ?: self::plain($task->description, 220)
                    ?: 'Đã hoàn thành hạng mục trong tuần.';

                return [
                    'title' => (string) $task->title,
                    'value' => $value,
                    'story_points' => self::points($task),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(Task $task, bool $rich): array
    {
        $row = [
            'title' => (string) $task->title,
            'status' => $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status,
            'progress' => (int) ($task->progress ?? 0),
            'story_points' => self::points($task),
            'assignee' => $task->assignee?->full_name,
            'epic' => $task->epic?->name,
            'priority' => $task->priority?->value,
        ];

        if ($rich) {
            $row['description'] = self::plain($task->description, 420);
            $row['completion_note'] = self::plain($task->completion_note, 280);
        }

        if ($task->completed_at) {
            $row['completed_at'] = $task->completed_at->format('d/m');
        }

        return $row;
    }

    public static function plain(?string $html, int $max = 400): string
    {
        if (! filled($html)) {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    public static function points(Task $task): ?float
    {
        if ($task->story_points === null || (float) $task->story_points <= 0) {
            return null;
        }

        return (float) $task->story_points;
    }
}
