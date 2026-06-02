<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
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
        $payload = ReportProjectSync::applyToPayload($data);

        return DailyReport::create([
            ...$payload,
            'employee_id' => $employeeId,
            'date' => $payload['date'] ?? now()->toDateString(),
            'status' => ReportStatus::Draft,
        ]);
    }
}
