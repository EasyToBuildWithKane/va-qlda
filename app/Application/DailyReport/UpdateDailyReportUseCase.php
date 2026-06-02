<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;

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
        // Keep the legacy single project_id in sync with the multi-select when
        // the projects field is part of this (possibly partial) update.
        if (array_key_exists('projects', $data)) {
            $data['project_id'] = $data['projects'][0]['id'] ?? null;
        }

        $report->update($data);

        return $report->refresh();
    }
}
