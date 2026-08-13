<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Observers\TaskObserver;
use App\Services\OnboardingService;
use App\Support\Congnghe\CongngheContentRepository;
use App\Support\Options\DepartmentOptions;
use App\Support\Options\EmployeeOptions;
use App\Support\Options\ProjectOptions;
use App\Support\Settings\SettingsRepository;
use App\Support\WeeklyReport\Contracts\WeeklyReportGenerator;
use App\Support\WeeklyReport\LlmWeeklyReportGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmployeeOptions::class);
        $this->app->singleton(ProjectOptions::class);
        $this->app->singleton(DepartmentOptions::class);
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(CongngheContentRepository::class);

        // Engine sinh báo cáo tuần: heuristic + LLM (nếu /settings/ai có API key).
        $this->app->bind(WeeklyReportGenerator::class, LlmWeeklyReportGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);

        // Keep the onboarding smart-context cache fresh: the first project / sprint
        // / task / member created flips a hint, so invalidate on create.
        foreach ([Project::class, Sprint::class, Task::class, Employee::class] as $model) {
            $model::created(static fn () => OnboardingService::forgetContext());
        }
    }
}
