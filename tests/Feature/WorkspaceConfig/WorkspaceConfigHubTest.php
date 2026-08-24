<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\DailyReport\DailyReportScoringConfig;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation\EvaluationCriterion;
use App\Models\SystemAccount;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Enums\SystemRole;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceConfigHubTest extends TestCase
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

    private function memberWithDept(string $code, string $name = 'Phòng ban test'): SystemAccount
    {
        $employee = Employee::factory()->create([
            'meta' => [
                'department_code' => $code,
                'department_name' => $name,
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

    public function test_super_admin_can_view_hub_with_directory_workspaces(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->has('workspaces', 2)
                ->has('summary')
                ->where('summary.total', 2)
                ->where('viewer.can_manage', true)
                ->where('workspaces.0.status_label', 'Chưa kích hoạt')
            );
    }

    public function test_hub_shows_criteria_readiness_even_without_profile(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::Department,
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
            'criteria_code' => 'H1',
            'criteria_name' => 'Tiêu chí HCNS',
            'category' => 'PB',
            'score_levels' => [
                ['label' => 'a', 'weight' => 1],
                ['label' => 'b', 'weight' => 2],
            ],
            'is_active' => true,
        ]);

        DailyReportScoringConfig::query()->create([
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
            'weights' => [
                'task_completion' => 0.3,
                'skill_score' => 0.2,
                'attitude_score' => 0.15,
                'expertise_score' => 0.2,
            ],
            'kaizen_bonus_max' => 2.0,
            'status' => DailyReportScoringConfig::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->where('summary.with_criteria', 1)
                ->where('summary.ready', 1)
                ->has('workspaces', 2)
                ->has('insights')
                ->has('coverage.modules')
                ->has('coverage.rows', 2)
                ->where('workspaces.0.department_code', 'HCNS')
                ->where('workspaces.0.status', 'missing')
                ->where('workspaces.0.status_label', 'Chưa kích hoạt')
                ->where('workspaces.0.criteria_count', 1)
                ->where('workspaces.0.has_criteria', true)
                ->where('workspaces.0.has_scoring_config', true)
                ->where('workspaces.0.readiness.key', 'ready')
                ->where('workspaces.0.readiness.label', 'Đã sẵn sàng')
                ->where('insights.0.code', 'has_criteria_missing_profile')
            );
    }

    public function test_super_admin_can_bulk_ensure_workspace_profiles(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/ensure-bulk', ['codes' => ['HCNS', 'CNTT']])
            ->assertRedirect();

        $this->assertSame(2, WorkspaceProfile::query()->count());
        $this->assertTrue(
            WorkspaceProfile::query()
                ->where('department_code', 'HCNS')
                ->where('status', WorkspaceProfileStatus::Active)
                ->exists()
        );
    }

    public function test_member_cannot_bulk_ensure_workspace_profiles(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->post('/workspace-config/ensure-bulk', ['codes' => ['HCNS']])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_notes_and_archive_profile(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $admin = $this->superAdmin();
        $this->actingAs($admin, 'system')
            ->post('/workspace-config/w/HCNS/ensure')
            ->assertRedirect();

        $this->actingAs($admin, 'system')
            ->patch('/workspace-config/w/HCNS', ['notes' => 'Ghi chú HCNS'])
            ->assertRedirect();

        $profile = WorkspaceProfile::query()->where('department_code', 'HCNS')->first();
        $this->assertSame('Ghi chú HCNS', $profile?->notes);

        $this->actingAs($admin, 'system')
            ->patch('/workspace-config/w/HCNS', ['status' => 'archived'])
            ->assertRedirect();

        $profile->refresh();
        $this->assertSame(WorkspaceProfileStatus::Archived, $profile->status);

        $this->actingAs($admin, 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workspaces.0.status', 'missing')
            );

        $this->actingAs($admin, 'system')
            ->get('/workspace-config?include_archived=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.include_archived', true)
                ->where('workspaces.0.status', 'archived')
                ->where('summary.archived', 1)
            );
    }

    public function test_workspace_show_includes_checklist(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS', 'Hành Chính Nhân Sự'), 'system')
            ->get('/workspace-config/w/HCNS')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Workspace/Show')
                ->has('checklist.items')
                ->has('checklist.done')
                ->has('checklist.total')
            );
    }

    public function test_member_sees_only_own_department_workspace(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS', 'Hành Chính Nhân Sự'), 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->has('workspaces', 1)
                ->where('workspaces.0.department_code', 'HCNS')
                ->where('workspaces.0.is_mine', true)
                ->where('viewer.can_manage', false)
                ->where('viewer.own_department_code', 'HCNS')
                ->where('viewer.own_department_name', 'Hành Chính Nhân Sự')
            );
    }

    public function test_member_resolves_department_from_name_without_code(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $employee = Employee::factory()->create([
            'meta' => [
                'department_name' => 'Phòng Hành Chính Nhân Sự',
            ],
        ]);
        $account = SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();

        $this->actingAs($account, 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->has('workspaces', 1)
                ->where('workspaces.0.department_code', 'HCNS')
                ->where('workspaces.0.is_mine', true)
                ->where('viewer.own_department_code', 'HCNS')
                ->where('viewer.own_department_name', 'Hành Chính Nhân Sự')
            );
    }

    public function test_member_resolves_department_from_workspace_pivot(): void
    {
        $dept = $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $employee = Employee::factory()->create(['meta' => null]);
        $employee->departments()->attach($dept->id, [
            'is_active' => true,
            'joined_at' => now()->toDateString(),
        ]);

        $account = SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();

        $this->actingAs($account, 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->has('workspaces', 1)
                ->where('workspaces.0.department_code', 'HCNS')
                ->where('viewer.own_department_code', 'HCNS')
                ->where('viewer.own_department_name', 'Hành Chính Nhân Sự')
            );
    }

    public function test_member_cannot_open_other_department_workspace(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->get('/workspace-config/w/CNTT')
            ->assertForbidden();
    }

    public function test_member_can_open_own_department_workspace(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS', 'Hành Chính Nhân Sự'), 'system')
            ->get('/workspace-config/w/HCNS')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Workspace/Show')
                ->where('workspace.department_code', 'HCNS')
                ->has('modules')
            );
    }

    public function test_super_admin_can_ensure_workspace_profile(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->superAdmin(), 'system')
            ->post('/workspace-config/w/HCNS/ensure')
            ->assertRedirect(route('workspace.profiles.show', ['departmentCode' => 'HCNS']));

        $profile = WorkspaceProfile::query()->where('department_code', 'HCNS')->first();
        $this->assertNotNull($profile);
        $this->assertSame(WorkspaceProfileStatus::Active, $profile->status);
    }

    public function test_member_cannot_ensure_workspace_profile(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->post('/workspace-config/w/HCNS/ensure')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/workspace-config')->assertRedirect('/login');
    }

    public function test_member_evaluation_index_is_scoped_to_own_department(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::General,
            'criteria_code' => 'G1',
            'criteria_name' => 'Tiêu chí chung',
            'category' => 'Chung',
            'score_levels' => [
                ['label' => 'a', 'weight' => 1],
                ['label' => 'b', 'weight' => 2],
            ],
            'is_active' => true,
        ]);

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::Department,
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
            'criteria_code' => 'H1',
            'criteria_name' => 'Tiêu chí HCNS',
            'category' => 'PB',
            'score_levels' => [
                ['label' => 'a', 'weight' => 1],
                ['label' => 'b', 'weight' => 2],
            ],
            'is_active' => true,
        ]);

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::Department,
            'department_code' => 'CNTT',
            'department_name' => 'Công nghệ thông tin',
            'criteria_code' => 'C1',
            'criteria_name' => 'Tiêu chí CNTT',
            'category' => 'PB',
            'score_levels' => [
                ['label' => 'a', 'weight' => 1],
                ['label' => 'b', 'weight' => 2],
            ],
            'is_active' => true,
        ]);

        $this->actingAs($this->memberWithDept('HCNS', 'Hành Chính Nhân Sự'), 'system')
            ->get('/workspace-config/evaluation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Evaluation/Index')
                ->where('summary.total', 2)
                ->where('viewer.forced_department_code', 'HCNS')
                ->where('can.manage', false)
            );
    }

    public function test_member_cannot_query_other_department_on_evaluation(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->seedDepartment('CNTT', 'Công nghệ thông tin');
        app(HrmDepartmentDirectory::class)->forget();

        $this->actingAs($this->memberWithDept('HCNS'), 'system')
            ->get('/workspace-config/evaluation?department_code=CNTT')
            ->assertForbidden();
    }
}
