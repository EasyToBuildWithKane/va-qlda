<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\WeeklyReport;

/**
 * Phân quyền báo cáo tuần. Quyền đến từ ma trận (weekly_report.*) HOẶC nhánh
 * sở hữu (PM dự án / thành viên dự án). Khoá nội dung khi đã duyệt được kiểm tra
 * thêm ở FormRequest/Controller.
 */
class WeeklyReportPolicy
{
    public function viewAny(SystemAccount $account): bool
    {
        return true;
    }

    public function view(SystemAccount $account, WeeklyReport $report): bool
    {
        return $account->allows('weekly_report.view')
            || $this->isManager($account, $report->project)
            || $this->isMember($account, $report->project);
    }

    /** Tạo / tổng hợp báo cáo cho một dự án (chưa có instance). */
    public function generate(SystemAccount $account, ?Project $project = null): bool
    {
        return $account->allows('weekly_report.generate')
            || ($project !== null && $this->isManager($account, $project));
    }

    public function update(SystemAccount $account, WeeklyReport $report): bool
    {
        return $account->allows('weekly_report.update')
            || $this->isManager($account, $report->project);
    }

    public function submit(SystemAccount $account, WeeklyReport $report): bool
    {
        return $account->allows('weekly_report.submit')
            || $this->isManager($account, $report->project);
    }

    public function approve(SystemAccount $account, WeeklyReport $report): bool
    {
        return $account->allows('weekly_report.approve')
            || $this->isManager($account, $report->project);
    }

    public function export(SystemAccount $account, WeeklyReport $report): bool
    {
        return $this->view($account, $report) || $account->allows('weekly_report.export');
    }

    private function isManager(SystemAccount $account, ?Project $project): bool
    {
        return $project !== null
            && $account->employee_id !== null
            && $account->employee_id === $project->manager_id;
    }

    private function isMember(SystemAccount $account, ?Project $project): bool
    {
        return $project !== null
            && $account->employee_id !== null
            && $project->members()->where('employee_id', $account->employee_id)->exists();
    }
}
