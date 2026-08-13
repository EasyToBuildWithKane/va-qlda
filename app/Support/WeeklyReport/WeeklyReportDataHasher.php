<?php

namespace App\Support\WeeklyReport;

/**
 * Sinh hash ổn định từ dữ liệu nguồn của kỳ → phát hiện dữ liệu đã đổi
 * sau lần generate gần nhất ("Regenerate available").
 */
class WeeklyReportDataHasher
{
    public function hash(WeeklyReportContext $context): string
    {
        $payload = [
            'tasks' => $context->tasks
                ->map(fn ($t) => [$t->id, $t->status->value, (string) ($t->actual_hours ?? ''), $t->updated_at?->timestamp])
                ->sortBy(fn ($row) => $row[0])->values()->all(),
            'worklogs' => $context->worklogs
                ->map(fn ($w) => [$w->id, (string) $w->hours])
                ->sortBy(fn ($row) => $row[0])->values()->all(),
            'blockers' => $context->blockers
                ->map(fn ($b) => [$b->id, $b->status->value, $b->severity->value])
                ->sortBy(fn ($row) => $row[0])->values()->all(),
            'feedbacks' => $context->feedbacks
                ->map(fn ($f) => [$f->id, $f->status->value])
                ->sortBy(fn ($row) => $row[0])->values()->all(),
            'activities' => $context->activities->count(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
