<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Models\SystemAccount;
use App\Support\DailyReportFieldContent;

class UpdateDailyReportUseCase
{
    public function __construct(
        private readonly SyncDailyReportSpawnedTasksUseCase $syncSpawnedTasks,
        private readonly SyncDailyReportRoutineTasksUseCase $syncRoutineTasks,
    ) {}

    /**
     * Update a draft report (autosave or explicit save). Editability is
     * enforced by DailyReportPolicy::update (draft + owner only).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(DailyReport $report, array $data, SystemAccount $actor): DailyReport
    {
        $report->update(
            DailyReportFieldContent::sanitizePayload(
                ReportProjectSync::applyToPayload($data),
            ),
        );

        $report = $this->syncSpawnedTasks->execute($report->refresh(), $actor);

        return $this->syncRoutineTasks->execute($report, $actor);
    }
}
