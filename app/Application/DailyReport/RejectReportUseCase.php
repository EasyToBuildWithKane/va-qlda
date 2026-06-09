<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Support\Enums\ReportStatus;

class RejectReportUseCase
{
    public function __construct(
        private readonly DailyReportReviewTelegramNotifier $reviewTelegram,
    ) {}

    /**
     * Reject a submitted report back to draft with reviewer notes, so the
     * author can amend and resubmit.
     *
     * @throws DailyReportException
     */
    public function execute(DailyReport $report, int $reviewerId, string $notes): DailyReport
    {
        if ($report->status !== ReportStatus::Submitted) {
            throw DailyReportException::notReviewable();
        }

        $report->forceFill([
            'status' => ReportStatus::Draft,
            'review_notes' => $notes,
            'submitted_at' => null,
        ])->save();

        activity('daily_report')
            ->performedOn($report)
            ->event('rejected')
            ->log('Daily report rejected');

        $this->reviewTelegram->notifyRejected($report, $reviewerId, $notes);

        return $report;
    }
}
