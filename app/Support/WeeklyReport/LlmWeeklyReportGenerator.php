<?php

namespace App\Support\WeeklyReport;

use App\Support\WeeklyReport\Contracts\WeeklyReportGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Engine báo cáo tuần: luôn tính KPI/rủi ro bằng heuristic, rồi (nếu có API key)
 * nhờ LLM viết lại văn bản điều hành. API lỗi → giữ bản heuristic.
 */
class LlmWeeklyReportGenerator implements WeeklyReportGenerator
{
    public function __construct(
        private readonly HeuristicWeeklyReportGenerator $heuristic,
        private readonly WeeklyReportLlmClient $llm,
    ) {}

    public function generate(WeeklyReportContext $context): GeneratedReport
    {
        $draft = $this->heuristic->generate($context);

        if (! $this->llm->isConfigured()) {
            return $draft;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(90);
        }

        try {
            $rewritten = $this->llm->rewrite($context, $draft);
        } catch (Throwable $e) {
            Log::warning('Weekly report LLM fallback to heuristic', [
                'provider' => $this->llm->provider(),
                'message' => $e->getMessage(),
            ]);

            return new GeneratedReport(
                executiveSummary: $draft->executiveSummary,
                aiSummary: $draft->aiSummary,
                kpi: $draft->kpi,
                meta: array_merge($draft->meta, [
                    'engine' => 'heuristic_fallback',
                    'llm_error' => true,
                ]),
                sections: $draft->sections,
            );
        }

        $sections = $draft->sections;
        foreach ($rewritten['sections'] as $key => $text) {
            if ($text !== '') {
                $sections[$key] = $text;
            }
        }

        $outcomes = $rewritten['outcomes'] !== []
            ? $rewritten['outcomes']
            : ($draft->meta['outcomes'] ?? []);

        return new GeneratedReport(
            executiveSummary: $rewritten['executive'] !== '' ? $rewritten['executive'] : $draft->executiveSummary,
            aiSummary: $rewritten['insight'] !== '' ? $rewritten['insight'] : $draft->aiSummary,
            kpi: $draft->kpi,
            meta: array_merge($draft->meta, [
                'engine' => 'llm',
                'llm_provider' => $this->llm->provider(),
                'llm_model' => $this->llm->model(),
                'outcomes' => $outcomes,
            ]),
            sections: $sections,
        );
    }
}
