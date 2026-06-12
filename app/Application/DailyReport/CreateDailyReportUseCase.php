<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Models\SystemAccount;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;

class CreateDailyReportUseCase
{
    public function __construct(
        private readonly SyncDailyReportSpawnedTasksUseCase $syncSpawnedTasks,
    ) {}

    /**
     * Create a draft report for an employee. The one-per-day rule is enforced
     * by the unique (employee_id, date) index and the StoreDailyReportRequest.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(int $employeeId, array $data, SystemAccount $actor): DailyReport
    {
        $payload = ReportProjectSync::applyToPayload($data);

        $report = DailyReport::create([
            ...$payload,
            'employee_id' => $employeeId,
            'date' => $payload['date'] ?? DailyReportCalendar::today(),
            'status' => ReportStatus::Draft,
        ]);

        return $this->syncSpawnedTasks->execute($report, $actor);
    }
}
