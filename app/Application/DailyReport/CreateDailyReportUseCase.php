<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Models\SystemAccount;
use App\Support\DailyReportCalendar;
use App\Support\DailyReportFieldContent;
use App\Support\Enums\ReportStatus;

class CreateDailyReportUseCase
{
    public function __construct(
        private readonly SyncDailyReportSpawnedTasksUseCase $syncSpawnedTasks,
        private readonly SyncDailyReportRoutineTasksUseCase $syncRoutineTasks,
    ) {}

    /**
     * Create a draft report for an employee. The one-per-day rule is enforced
     * by the unique (employee_id, date) index and the StoreDailyReportRequest.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(int $employeeId, array $data, SystemAccount $actor): DailyReport
    {
        $payload = DailyReportFieldContent::sanitizePayload(
            ReportProjectSync::applyToPayload($data),
        );

        $report = DailyReport::create([
            ...$payload,
            'employee_id' => $employeeId,
            'date' => $payload['date'] ?? DailyReportCalendar::today(),
            'status' => ReportStatus::Draft,
        ]);

        $report = $this->syncSpawnedTasks->execute($report, $actor);

        return $this->syncRoutineTasks->execute($report, $actor);
    }
}
