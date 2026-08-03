<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Evaluation\HrmDepartmentDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReportScoringConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
            'hrm.api.verify_ssl' => false,
        ]);
        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'count' => 0, 'per_page' => 100]],
            ]),
        ]);
    }

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function memberWithDept(string $code): SystemAccount
    {
        $employee = Employee::factory()->create([
            'meta' => [
                'department_code' => $code,
                'department_name' => 'PB '.$code,
            ],
        ]);

        return SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();
    }

    private function seedDepartment(string $code, string $name): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $name,
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_save_department_scoring_weights(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->superAdmin(), 'system')
            ->put('/workspace-config/daily-report-scoring', [
                'department_code' => 'HCNS',
                'department_name' => 'Hành Chính Nhân Sự',
                'weights' => [
                    'task_completion' => 0.4,
                    'skill_score' => 0.2,
                    'attitude_score' => 0.2,
                    'expertise_score' => 0.2,
                ],
                'kaizen_bonus_max' => 1.5,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_report_scoring_configs', [
            'department_code' => 'HCNS',
            'status' => 'active',
        ]);

        $config = DailyReportScoringConfig::query()->forDepartment('HCNS')->first();
        $this->assertSame(0.4, (float) $config->weights['task_completion']);
        $this->assertSame(1.5, (float) $config->kaizen_bonus_max);
    }

    public function test_member_cannot_manage_scoring_config(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->put('/workspace-config/daily-report-scoring', [
                'department_code' => 'HCNS',
                'weights' => [
                    'task_completion' => 0.4,
                    'skill_score' => 0.2,
                    'attitude_score' => 0.2,
                    'expertise_score' => 0.2,
                ],
                'kaizen_bonus_max' => 2,
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_open_other_department_scoring_page(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->get('/workspace-config/daily-report-scoring?department_code=CNTT')
            ->assertForbidden();
    }

    public function test_edit_page_renders_for_super_admin(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/daily-report-scoring?department_code=HCNS')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/DailyReportScoring/Edit')
                ->where('departmentCode', 'HCNS')
                ->has('systemDefaults.weights')
                ->where('can.manage', true)
            );
    }
}
