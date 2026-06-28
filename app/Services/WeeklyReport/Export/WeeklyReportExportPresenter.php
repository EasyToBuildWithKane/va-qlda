<?php

namespace App\Services\WeeklyReport\Export;

use App\Models\WeeklyReport;
use App\Models\WeeklyReportSection;
use App\Support\Enums\WeeklyReportSection as SectionEnum;

/**
 * Chuẩn hoá dữ liệu báo cáo tuần thành mảng phẳng cho cả PDF (Blade) lẫn DOCX (PhpWord).
 */
class WeeklyReportExportPresenter
{
    private const KPI_LABELS = [
        'sprint_progress' => ['Tiến độ Sprint', '%'],
        'completed_tasks' => ['Hoàn thành', ''],
        'total_tasks' => ['Tổng công việc', ''],
        'remaining_tasks' => ['Còn lại', ''],
        'overdue' => ['Quá hạn', ''],
        'blocked' => ['Bị chặn', ''],
        'open_issues' => ['Vướng mắc mở', ''],
        'feedback' => ['Phản hồi', ''],
        'critical_bugs' => ['Lỗi nghiêm trọng', ''],
        'worklog_hours' => ['Giờ công', 'h'],
        'team_velocity' => ['Team Velocity', '%'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(WeeklyReport $report): array
    {
        $report->loadMissing(['sprint', 'sections', 'approvedBy', 'generatedBy']);

        $sections = $report->sections->keyBy(fn (WeeklyReportSection $s) => $s->section->value);
        $kpi = $report->kpi_snapshot ?? [];
        $meta = $report->meta ?? [];

        return [
            'code' => $report->code(),
            'project' => $report->project?->name ?? '',
            'sprint' => $report->sprint?->name ?? 'Ngoài Sprint',
            'week_number' => $report->week_number,
            'period' => $report->week_start->format('d/m/Y').' – '.$report->week_end->format('d/m/Y'),
            'status_label' => $report->status->label(),
            'executive_summary' => $report->executive_summary ?? '',
            'ai_summary' => $report->ai_summary ?? '',
            'kpi' => $this->kpiRows($kpi),
            'cards' => [
                ['label' => 'Kết quả thực hiện', 'lines' => $this->lines($sections, SectionEnum::Result)],
                ['label' => 'Tình hình hiện tại', 'lines' => $this->lines($sections, SectionEnum::Current)],
                ['label' => 'Kế hoạch tiếp theo', 'lines' => $this->lines($sections, SectionEnum::Next)],
            ],
            'risks' => $this->risks($meta),
            'feedback' => $meta['feedback']['breakdown'] ?? [],
            'activity' => $this->lines($sections, SectionEnum::Activity),
            'generated_by' => $report->generatedBy?->display_name,
            'generated_at' => $report->generated_at?->format('d/m/Y H:i'),
            'approved_by' => $report->approvedBy?->display_name,
            'approved_at' => $report->approved_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<int, array{label:string, value:string}>
     */
    private function kpiRows(array $kpi): array
    {
        $rows = [];
        foreach (self::KPI_LABELS as $key => [$label, $suffix]) {
            if (! array_key_exists($key, $kpi)) {
                continue;
            }
            $rows[] = ['label' => $label, 'value' => $kpi[$key].$suffix];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, WeeklyReportSection>  $sections
     * @return array<int, string>
     */
    private function lines($sections, SectionEnum $key): array
    {
        $content = $sections->get($key->value)?->content ?? '';

        return collect(explode("\n", $content))
            ->map(fn ($l) => trim(preg_replace('/^•\s*/u', '', $l)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{level:string, label:string, reason:string}>
     */
    private function risks(array $meta): array
    {
        $levelLabel = ['high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp'];

        return collect($meta['risk']['risks'] ?? [])
            ->map(fn ($r) => [
                'level' => $levelLabel[$r['level']] ?? $r['level'],
                'label' => $r['label'] ?? '',
                'reason' => $r['reason'] ?? '',
            ])
            ->all();
    }
}
