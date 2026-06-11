<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\AiAccount;
use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Department;
use App\Models\Feedback;
use App\Models\OrgTeam;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Policies\AiAccountPolicy;
use App\Policies\AiPaymentRequestPolicy;
use App\Policies\AiPurchaseProposalPolicy;
use App\Policies\BlockerPolicy;
use App\Policies\BugPolicy;
use App\Policies\DailyReportPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\FeedbackPolicy;
use App\Policies\OrgTeamPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SystemSettingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
        OrgTeam::class => OrgTeamPolicy::class,
        Bug::class => BugPolicy::class,
        Feedback::class => FeedbackPolicy::class,
        Blocker::class => BlockerPolicy::class,
        AiAccount::class => AiAccountPolicy::class,
        AiPaymentRequest::class => AiPaymentRequestPolicy::class,
        AiPurchaseProposal::class => AiPurchaseProposalPolicy::class,
        SystemSetting::class => SystemSettingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
