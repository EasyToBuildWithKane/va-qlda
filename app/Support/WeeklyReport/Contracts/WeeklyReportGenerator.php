<?php

namespace App\Support\WeeklyReport\Contracts;

use App\Support\WeeklyReport\GeneratedReport;
use App\Support\WeeklyReport\WeeklyReportContext;

/**
 * Hợp đồng cho engine sinh báo cáo tuần.
 *
 * Mặc định bind tới {@see \App\Support\WeeklyReport\HeuristicWeeklyReportGenerator}
 * (rule-based, không gọi LLM). Sau này có thể bind một implement gọi Claude API
 * mà không phải sửa Service/Controller.
 */
interface WeeklyReportGenerator
{
    public function generate(WeeklyReportContext $context): GeneratedReport;
}
