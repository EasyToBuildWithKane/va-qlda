<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Services\WeeklyReport\WeeklyReportPresenter;
use App\Services\WeeklyReport\WeeklyReportService;
use App\Support\Enums\SystemRole;
use Illuminate\Database\Seeder;

/**
 * Seed một báo cáo tuần demo cho dự án đầu tiên có Sprint — đủ trạng thái để
 * xem trên /projects/{id}?tab=weekly. An toàn để chạy lại (idempotent).
 */
class WeeklyReportSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->whereHas('sprints')->with('sprints')->first();
        if (! $project) {
            $this->command->warn('WeeklyReportSeeder: chưa có dự án nào có Sprint — bỏ qua.');

            return;
        }

        $actor = SystemAccount::query()
            ->whereIn('role', [SystemRole::SuperAdmin->value, SystemRole::Admin->value])
            ->first();
        if (! $actor) {
            $this->command->warn('WeeklyReportSeeder: chưa có tài khoản admin — bỏ qua.');

            return;
        }

        $service = app(WeeklyReportService::class);
        $presenter = app(WeeklyReportPresenter::class);
        $sprint = $presenter->activeSprint($project);

        $report = $service->createForWeek($project, $sprint, 1, $actor);

        $this->command->info("WeeklyReportSeeder: đã tạo báo cáo {$report->code()} cho dự án {$project->name}.");
    }
}
