<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Exceptions\DailyReportException;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\SystemAccount;
use App\Support\DailyReportCalendar;
use App\Support\Enums\ReportStatus;
use App\Support\SecurityAuditLogger;
use Illuminate\Support\Facades\DB;

class RecallDailyReportUseCase
{
    /**
     * Let the owner pull a just-submitted report back to draft so they can fix
     * it without asking a reviewer to reject. Bounded to the same business day
     * to keep it a "take-back", not a way to reopen historical reports.
     *
     * The frozen snapshot is cleared; a fresh one is re-frozen on re-submit.
     * `recalled_at`/`recall_count` are tracked so repeated take-backs are
     * visible. Authorization (ownership) is enforced by the policy; this guards
     * the state machine and time window with user-facing domain messages.
     *
     * @throws DailyReportException
     */
    public function execute(DailyReport $report, ?SystemAccount $actor = null, ?string $reason = null): DailyReport
    {
        if ($report->status !== ReportStatus::Submitted) {
            throw DailyReportException::cannotRecall();
        }

        if (! DailyReportCalendar::isToday($report->date)) {
            throw DailyReportException::recallExpired();
        }

        $reason = $reason !== null ? trim($reason) : null;
        $reason = $reason === '' ? null : $reason;

        DB::transaction(function () use ($report, $reason) {
            $report->forceFill([
                'status' => ReportStatus::Draft,
                'submitted_at' => null,
                'is_late' => false,
                'task_status_snapshot' => null,
                'recalled_at' => DailyReportCalendar::now(),
                'recall_count' => (int) $report->recall_count + 1,
            ])->save();

            activity('daily_report')
                ->performedOn($report)
                ->event('recalled')
                ->withProperties($reason !== null ? ['reason' => $reason] : [])
                ->log('Daily report recalled');
        });

        if ($actor !== null) {
            SecurityAuditLogger::dailyReport($actor, 'recalled', $report->id, array_filter([
                'date' => $report->date->toDateString(),
                'reason' => $reason,
            ], fn ($v) => $v !== null));
        }

        return $report;
    }
}
