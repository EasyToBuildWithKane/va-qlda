<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Domain\DailyReport\Models\DailyReport;
use App\Models\AiAccount;
use App\Models\Blocker;
use App\Models\CongngheSection;
use App\Models\CongngheSoftwareProposal;
use App\Models\Contract;
use App\Models\Credential;
use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationTemplate;
use App\Models\Feedback;
use App\Models\KbArticle;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\WeeklyReport;
use App\Policies\AiAccountPolicy;
use App\Policies\BlockerPolicy;
use App\Policies\CongngheContentPolicy;
use App\Policies\CongngheSoftwareProposalPolicy;
use App\Policies\ContractPolicy;
use App\Policies\CredentialPolicy;
use App\Policies\DailyReportPolicy;
use App\Policies\DailyReportScoringConfigPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationCriterionPolicy;
use App\Policies\EvaluationFormPolicy;
use App\Policies\EvaluationTemplatePolicy;
use App\Policies\FeedbackPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\TaskPolicy;
use App\Policies\VendorPolicy;
use App\Policies\WeeklyReportPolicy;
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
        Task::class => TaskPolicy::class,
        Department::class => DepartmentPolicy::class,
        Employee::class => EmployeePolicy::class,
        Blocker::class => BlockerPolicy::class,
        Feedback::class => FeedbackPolicy::class,
        WeeklyReport::class => WeeklyReportPolicy::class,
        AiAccount::class => AiAccountPolicy::class,
        SystemSetting::class => SystemSettingPolicy::class,
        EvaluationCriterion::class => EvaluationCriterionPolicy::class,
        EvaluationTemplate::class => EvaluationTemplatePolicy::class,
        EvaluationForm::class => EvaluationFormPolicy::class,
        DailyReportScoringConfig::class => DailyReportScoringConfigPolicy::class,
        CongngheSection::class => CongngheContentPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
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
        // Super admin is unconditionally all-powerful — short-circuit every
        // gate/policy. Returning null lets other roles fall through to policies.
        Gate::before(fn (SystemAccount $account) => $account->isSuperAdmin() ? true : null);

        // Module Hiệu suất & Audit — điều khiển qua ma trận phân quyền.
        Gate::define('performance.view', fn (SystemAccount $account) => $account->allows('performance.view'));
    }
}
