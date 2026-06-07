<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;

class SubmitDailyReportUseCase
{
    /**
     * Submit a draft report for review. Enforces working-day gating and flags
     * late submissions (after the configured cutoff time).
     *
     * @throws DailyReportException
     */
    public function execute(DailyReport $report): DailyReport
    {
        if ($report->status !== ReportStatus::Draft) {
            throw DailyReportException::notSubmittable();
        }

        $now = DailyReportCalendar::now();

        if (! in_array($now->isoWeekday(), config('daily_report.working_days'), true)) {
            throw DailyReportException::notWorkingDay();
        }

        $report->forceFill([
            'status' => ReportStatus::Submitted,
            'submitted_at' => $now,
            'is_late' => $now->format('H:i') >= config('daily_report.late_after'),
            'review_notes' => null,
        ])->save();

        activity('daily_report')
            ->performedOn($report)
            ->event('submitted')
            ->log('Daily report submitted');

        return $report;
    }
}
