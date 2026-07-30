<?php

namespace Tests\Feature\WorkspaceConfig;

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
                ->where('viewer.can_manage', false)
                ->where('viewer.own_department_code', 'HCNS')
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
            'scoring_type' => 'scale',
            'score_1' => 'a',
            'score_2' => 'b',
            'score_3' => 'c',
            'score_4' => 'd',
            'score_5' => 'e',
            'is_active' => true,
        ]);

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::Department,
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
            'criteria_code' => 'H1',
            'criteria_name' => 'Tiêu chí HCNS',
            'category' => 'PB',
            'scoring_type' => 'scale',
            'score_1' => 'a',
            'score_2' => 'b',
            'score_3' => 'c',
            'score_4' => 'd',
            'score_5' => 'e',
            'is_active' => true,
        ]);

        EvaluationCriterion::query()->create([
            'scope' => EvaluationCriterionScope::Department,
            'department_code' => 'CNTT',
            'department_name' => 'Công nghệ thông tin',
            'criteria_code' => 'C1',
            'criteria_name' => 'Tiêu chí CNTT',
            'category' => 'PB',
            'scoring_type' => 'scale',
            'score_1' => 'a',
            'score_2' => 'b',
            'score_3' => 'c',
            'score_4' => 'd',
            'score_5' => 'e',
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
