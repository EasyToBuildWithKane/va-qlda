<?php

namespace App\Policies;

use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\SystemAccount;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;

class DailyReportScoringConfigPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return $account->allows('workspace.daily_report_scoring.view')
            || $account->allows('workspace.daily_report_scoring.manage')
            || $account->allows('workspace.hub.view');
    }

    public function view(SystemAccount $account, DailyReportScoringConfig $config): bool
    {
        if (! $this->viewAny($account)) {
            return false;
        }

        $scope = app(WorkspaceScopeResolver::class);
        if ($scope->canManageAll($account)
            || $account->allows('workspace.daily_report_scoring.manage')
            || $account->allows('workspace.daily_report_scoring.view')) {
            return true;
        }

        return $scope->canAccess($account, $config->department_code);
    }

    public function update(SystemAccount $account): bool
    {
        return $account->allows('workspace.daily_report_scoring.manage');
    }
}
