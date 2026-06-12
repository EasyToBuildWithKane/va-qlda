<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Support\ReportProjectSync;
use App\Models\SystemAccount;

class UpdateDailyReportUseCase
{
    public function __construct(
        private readonly SyncDailyReportSpawnedTasksUseCase $syncSpawnedTasks,
    ) {}

    /**
     * Update a draft report (autosave or explicit save). Editability is
     * enforced by DailyReportPolicy::update (draft + owner only).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(DailyReport $report, array $data, SystemAccount $actor): DailyReport
    {
        $report->update(ReportProjectSync::applyToPayload($data));

        return $this->syncSpawnedTasks->execute($report->refresh(), $actor);
    }
}
