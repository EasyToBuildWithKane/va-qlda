<?php

namespace App\Policies;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\SystemAccount;
use App\Support\Enums\ReportStatus;

class DailyReportPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, DailyReport $report): bool
    {
        return $account->allows('daily_report.view')
            || $this->owns($account, $report);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->employee_id !== null
            && $account->allows('daily_report.create');
    }

    public function update(SystemAccount $account, DailyReport $report): bool
    {
        return $report->isEditable()
            && ($this->owns($account, $report) || $account->allows('daily_report.update'));
    }

    public function submit(SystemAccount $account, DailyReport $report): bool
    {
        return $report->isEditable() && $this->owns($account, $report);
    }

    public function score(SystemAccount $account, DailyReport $report): bool
    {
        return $account->allows('daily_report.review')
            && $account->employee_id !== null
            && $report->status === ReportStatus::Submitted;
    }

    public function reject(SystemAccount $account, DailyReport $report): bool
    {
        return $this->score($account, $report);
    }

    public function delete(SystemAccount $account, DailyReport $report): bool
    {
        return $report->isEditable()
            && ($this->owns($account, $report) || $account->allows('daily_report.delete'));
    }

    private function owns(SystemAccount $account, DailyReport $report): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $report->employee_id;
    }
}
