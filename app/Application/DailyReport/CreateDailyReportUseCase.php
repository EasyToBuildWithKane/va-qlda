<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Support\Enums\ReportStatus;

class CreateDailyReportUseCase
{
    /**
     * Create a draft report for an employee. The one-per-day rule is enforced
     * by the unique (employee_id, date) index and the StoreDailyReportRequest.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(int $employeeId, array $data): DailyReport
    {
        return DailyReport::create([
            ...$data,
            'employee_id' => $employeeId,
            'date' => $data['date'] ?? now()->toDateString(),
            // Keep the legacy single project_id in sync with the multi-select so
            // the Report History project filter keeps working.
            'project_id' => $data['projects'][0]['id'] ?? null,
            'status' => ReportStatus::Draft,
        ]);
    }
}
