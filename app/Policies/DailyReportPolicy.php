<?php

namespace App\Policies;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\SystemAccount;
use App\Support\Enums\ReportStatus;
use App\Support\Enums\SystemRole;

class DailyReportPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, DailyReport $report): bool
    {
        return $this->isReviewer($account)
            || $account->role === SystemRole::Viewer
            || $this->owns($account, $report);
    }

    public function create(SystemAccount $account): bool
    {
        return $account->employee_id !== null
            && in_array($account->role, [SystemRole::Admin, SystemRole::Lead, SystemRole::Member], true);
    }

    public function update(SystemAccount $account, DailyReport $report): bool
    {
        return $report->isEditable()
            && ($this->owns($account, $report) || $account->role === SystemRole::Admin);
    }

    public function submit(SystemAccount $account, DailyReport $report): bool
    {
        return $report->isEditable() && $this->owns($account, $report);
    }

    public function score(SystemAccount $account, DailyReport $report): bool
    {
        return $this->isReviewer($account)
            && $account->employee_id !== null
            && $report->status === ReportStatus::Submitted;
    }

    public function reject(SystemAccount $account, DailyReport $report): bool
    {
        return $this->score($account, $report);
    }

    private function owns(SystemAccount $account, DailyReport $report): bool
    {
        return $account->employee_id !== null
            && $account->employee_id === $report->employee_id;
    }

    private function isReviewer(SystemAccount $account): bool
    {
        return in_array($account->role, [SystemRole::Admin, SystemRole::Lead], true);
    }
}
