<?php

namespace App\Support\WeeklyReport;

use App\Support\WeeklyReport\Contracts\WeeklyReportGenerator;

/**
 * Engine sinh báo cáo tuần theo luật (rule-based) — KHÔNG gọi LLM ngoài.
 *
 * Đọc trực tiếp dữ liệu kỳ (khoảng ngày trên dự án) qua {@see WeeklyReportContext} và tổng hợp thành
 * KPI + văn bản báo cáo quản trị tiếng Việt. Đứng sau interface
 * {@see WeeklyReportGenerator} để sau này có thể thay bằng implement gọi Claude API.
 */
class HeuristicWeeklyReportGenerator implements WeeklyReportGenerator
{
    public function __construct(
        private readonly WeeklyReportKpiBuilder $kpiBuilder,
        private readonly WeeklyReportRiskAssessor $riskAssessor,
        private readonly WeeklyReportFeedbackClassifier $feedbackClassifier,
        private readonly WeeklyReportNarrator $narrator,
    ) {}

    public function generate(WeeklyReportContext $context): GeneratedReport
    {
        $kpi = $this->kpiBuilder->build($context);
        $risk = $this->riskAssessor->assess($context, $kpi);
        $feedback = $this->feedbackClassifier->classify($context->feedbacks);
        $narrative = $this->narrator->narrate($context, $kpi, $risk, $feedback);

        return new GeneratedReport(
            executiveSummary: $narrative['executive'],
            aiSummary: $narrative['insight'],
            kpi: $kpi,
            meta: [
                'risk' => $risk,
                'feedback' => $feedback,
                'engine' => 'heuristic',
                'outcomes' => WeeklyReportTaskFacts::heuristicOutcomes($context),
                'contributors' => WeeklyReportTaskFacts::periodContributors($context),
            ],
            sections: $narrative['sections'],
        );
    }
}
