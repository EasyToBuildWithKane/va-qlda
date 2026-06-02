<?php

namespace Database\Factories;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Models\Employee;
use App\Support\Enums\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReportScore>
 */
class DailyReportScoreFactory extends Factory
{
    protected $model = DailyReportScore::class;

    public function definition(): array
    {
        return [
            'report_id' => DailyReport::factory(),
            'task_completion' => 8,
            'skill_score' => 8,
            'attitude_score' => 8,
            'kaizen_score' => 8,
            'expertise_score' => 8,
            'total_score' => 8,
            'grade' => Grade::A,
            'reviewer_id' => Employee::factory(),
            'notes' => null,
        ];
    }
}
