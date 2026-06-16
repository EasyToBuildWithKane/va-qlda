<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\Blocker;
use App\Models\CoachingCourse;
use App\Models\CongngheSection;
use App\Models\CongngheSoftwareProposal;
use App\Models\Contract;
use App\Models\Credential;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\KbArticle;
use App\Models\OrgTeam;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\SystemSetting;
use App\Models\Vendor;
use App\Policies\AiAccountPolicy;
use App\Policies\AiPaymentRequestPolicy;
use App\Policies\AiPurchaseProposalPolicy;
use App\Policies\BlockerPolicy;
use App\Policies\CoachingCoursePolicy;
use App\Policies\CongngheContentPolicy;
use App\Policies\CongngheSoftwareProposalPolicy;
use App\Policies\ContractPolicy;
use App\Policies\CredentialPolicy;
use App\Policies\DailyReportPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\FeedbackPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\OrgTeamPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\VendorPolicy;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * Explicit mapping is required because domain models live outside
     * App\Models, so policy auto-discovery does not apply.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        DailyReport::class => DailyReportPolicy::class,
        Project::class => ProjectPolicy::class,
        Department::class => DepartmentPolicy::class,
        Employee::class => EmployeePolicy::class,
        OrgTeam::class => OrgTeamPolicy::class,
        Blocker::class => BlockerPolicy::class,
        Feedback::class => FeedbackPolicy::class,
        AiAccount::class => AiAccountPolicy::class,
        AiPaymentRequest::class => AiPaymentRequestPolicy::class,
        AiPurchaseProposal::class => AiPurchaseProposalPolicy::class,
        SystemSetting::class => SystemSettingPolicy::class,
        CongngheSection::class => CongngheContentPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
        CoachingCourse::class => CoachingCoursePolicy::class,
        CongngheSoftwareProposal::class => CongngheSoftwareProposalPolicy::class,
        Credential::class => CredentialPolicy::class,
        Contract::class => ContractPolicy::class,
        Vendor::class => VendorPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Module Hiệu suất & Audit — chỉ vai trò quản lý (admin/lead/viewer) xem được.
        Gate::define('performance.view', fn (SystemAccount $account) => $account->hasRole(
            SystemRole::Admin,
            SystemRole::Lead,
            SystemRole::Viewer,
        ));
    }
}
