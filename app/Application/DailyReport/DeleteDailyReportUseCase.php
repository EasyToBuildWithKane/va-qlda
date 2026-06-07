<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;

class DeleteDailyReportUseCase
{
    /**
     * Permanently remove a draft report. Editability is enforced by
     * DailyReportPolicy::delete (draft + owner or admin).
     *
     * @throws DailyReportException
     */
    public function execute(DailyReport $report): void
    {
        if (! $report->isEditable()) {
            throw DailyReportException::notDeletable();
        }

        activity('daily_report')
            ->performedOn($report)
            ->event('deleted')
            ->log('Daily report deleted');

        $report->delete();
    }
}
