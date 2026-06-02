<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;

class UpdateDailyReportUseCase
{
    /**
     * Update a draft report (autosave or explicit save). Editability is
     * enforced by DailyReportPolicy::update (draft + owner only).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(DailyReport $report, array $data): DailyReport
    {
        $report->update(ReportProjectSync::applyToPayload($data));

        return $report->refresh();
    }
}
