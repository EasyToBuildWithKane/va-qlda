<?php

namespace App\Providers;

use App\Support\Options\DepartmentOptions;
use App\Support\Options\EmployeeOptions;
use App\Support\Options\ProjectOptions;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmployeeOptions::class);
        $this->app->singleton(ProjectOptions::class);
        $this->app->singleton(DepartmentOptions::class);
        $this->app->singleton(SettingsRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
