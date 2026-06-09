<?php

namespace Database\Factories;

use App\Domain\DailyReport\Models\DailyReport;
use App\Models\Employee;
use App\Support\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReport>
 */
class DailyReportFactory extends Factory
{
    protected $model = DailyReport::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'project_id' => null,
            'date' => now()->toDateString(),
            'title' => fake()->sentence(4),
            'goals_today' => fake()->paragraph(),
            'progress_update' => fake()->paragraph(),
            'plan_tomorrow' => fake()->paragraph(),
            'status' => ReportStatus::Draft,
            'is_late' => false,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Reviewed,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
        ]);
    }
}
