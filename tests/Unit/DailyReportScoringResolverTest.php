<?php

namespace Tests\Unit;

use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Employee;
use App\Support\DailyReport\DailyReportScoringResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportScoringResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_system_config_when_no_department_row(): void
    {
        $employee = Employee::factory()->create([
            'meta' => ['department_code' => 'HCNS', 'department_name' => 'Hành chính'],
        ]);

        $rubric = app(DailyReportScoringResolver::class)->forEmployee($employee);

        $this->assertSame(DailyReportScoringResolver::SOURCE_SYSTEM, $rubric['source']);
        $this->assertSame('HCNS', $rubric['department_code']);
        $this->assertSame(0.30, $rubric['weights']['task_completion']);
        $this->assertSame(2.0, $rubric['kaizen_bonus_max']);
        $this->assertNull($rubric['config_id']);
    }

    public function test_uses_active_department_config(): void
    {
        DailyReportScoringConfig::query()->create([
            'department_code' => 'CNTT',
            'department_name' => 'Công nghệ',
            'weights' => [
                'task_completion' => 0.5,
                'skill_score' => 0.2,
                'attitude_score' => 0.1,
                'expertise_score' => 0.2,
            ],
            'kaizen_bonus_max' => 1.5,
            'status' => DailyReportScoringConfig::STATUS_ACTIVE,
        ]);

        $employee = Employee::factory()->create([
            'meta' => ['department_code' => 'CNTT'],
        ]);

        $rubric = app(DailyReportScoringResolver::class)->forEmployee($employee);

        $this->assertSame(DailyReportScoringResolver::SOURCE_DEPARTMENT, $rubric['source']);
        $this->assertSame(0.5, $rubric['weights']['task_completion']);
        $this->assertSame(1.5, $rubric['kaizen_bonus_max']);
        $this->assertNotNull($rubric['config_id']);
    }

    public function test_ignores_draft_config(): void
    {
        DailyReportScoringConfig::query()->create([
            'department_code' => 'HCNS',
            'department_name' => 'HCNS',
            'weights' => [
                'task_completion' => 0.9,
                'skill_score' => 0.05,
                'attitude_score' => 0.05,
                'expertise_score' => 0.05,
            ],
            'kaizen_bonus_max' => 3.0,
            'status' => DailyReportScoringConfig::STATUS_DRAFT,
        ]);

        $employee = Employee::factory()->create([
            'meta' => ['department_code' => 'HCNS'],
        ]);

        $rubric = app(DailyReportScoringResolver::class)->forEmployee($employee);

        $this->assertSame(DailyReportScoringResolver::SOURCE_SYSTEM, $rubric['source']);
        $this->assertSame(2.0, $rubric['kaizen_bonus_max']);
    }
}
