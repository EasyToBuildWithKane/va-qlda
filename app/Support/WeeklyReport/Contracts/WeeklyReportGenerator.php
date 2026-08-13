<?php

namespace App\Support\WeeklyReport\Contracts;

use App\Support\WeeklyReport\GeneratedReport;
use App\Support\WeeklyReport\WeeklyReportContext;

/**
 * Hợp đồng cho engine sinh báo cáo tuần.
 *
 * Mặc định bind tới {@see \App\Support\WeeklyReport\LlmWeeklyReportGenerator}
 * (heuristic + LLM khi Super Admin lưu API key tại /settings/ai).
 */
interface WeeklyReportGenerator
{
    public function generate(WeeklyReportContext $context): GeneratedReport;
}
