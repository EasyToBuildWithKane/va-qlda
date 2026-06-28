<?php

namespace App\Support\WeeklyReport;

/**
 * Kết quả engine sinh ra cho một báo cáo tuần.
 */
final class GeneratedReport
{
    /**
     * @param  array<string, mixed>  $kpi  Snapshot KPI cards
     * @param  array<string, mixed>  $meta  Rủi ro, phân loại phản hồi, điểm nổi bật
     * @param  array<string, string>  $sections  section value => nội dung văn bản
     */
    public function __construct(
        public readonly string $executiveSummary,
        public readonly string $aiSummary,
        public readonly array $kpi,
        public readonly array $meta,
        public readonly array $sections,
    ) {}
}
