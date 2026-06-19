<?php

namespace App\Support;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\SystemAccount;
use Spatie\Activitylog\Models\Activity;

/**
 * Build a clean, user-facing audit timeline for a single daily report from the
 * Spatie activity_log entries written by the report use cases.
 *
 * Only meaningful lifecycle events are surfaced — raw autosave `updated`
 * entries (the editor saves a draft every ~30s) are filtered out so the
 * timeline reads as a workflow history, not a change log.
 */
class DailyReportTimeline
{
    /**
     * event key → [Vietnamese label, severity-ish tone for the dot colour].
     *
     * @var array<string, array{label:string, tone:string}>
     */
    private const EVENTS = [
        'created' => ['label' => 'Tạo báo cáo', 'tone' => 'slate'],
        'submitted' => ['label' => 'Nộp báo cáo chờ duyệt', 'tone' => 'amber'],
        'recalled' => ['label' => 'Rút lại để chỉnh sửa', 'tone' => 'amber'],
        'rejected' => ['label' => 'Trả lại để chỉnh sửa', 'tone' => 'rose'],
        'reviewed' => ['label' => 'Chấm điểm & duyệt', 'tone' => 'emerald'],
        'addendum_created' => ['label' => 'Bổ sung nội dung', 'tone' => 'sky'],
        'deleted' => ['label' => 'Xoá báo cáo', 'tone' => 'rose'],
    ];

    /**
     * @return array<int, array{event:string, label:string, tone:string, actor:?string, at:?string, reason:?string, grade:?string, total:?float}>
     */
    public static function for(DailyReport $report): array
    {
        $events = array_keys(self::EVENTS);

        return Activity::query()
            ->where('log_name', 'daily_report')
            ->where('subject_type', $report->getMorphClass())
            ->where('subject_id', $report->getKey())
            ->whereIn('event', $events)
            ->with('causer')
            ->oldest()
            ->get()
            ->map(function (Activity $activity): array {
                $meta = self::EVENTS[$activity->event] ?? ['label' => $activity->event, 'tone' => 'slate'];
                $props = $activity->properties ?? collect();
                $causer = $activity->causer;

                return [
                    'event' => (string) $activity->event,
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'actor' => $causer instanceof SystemAccount ? $causer->display_name : null,
                    'at' => $activity->created_at?->toIso8601String(),
                    'reason' => self::stringProp($props->get('reason')),
                    'grade' => self::stringProp($props->get('grade')),
                    'total' => is_numeric($props->get('total')) ? (float) $props->get('total') : null,
                ];
            })
            ->all();
    }

    private static function stringProp(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
