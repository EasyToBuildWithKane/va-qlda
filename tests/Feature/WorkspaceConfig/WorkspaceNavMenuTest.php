<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\SystemRole;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\Navigation;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use App\Support\WorkspaceConfig\WorkspaceNavModuleMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceNavMenuTest extends TestCase
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
            'va.menu_hidden_groups' => [],
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

    private function ensureProfile(string $code, string $name, ?array $enabledNav = null): WorkspaceProfile
    {
        return WorkspaceProfile::query()->create([
            'department_code' => $code,
            'department_name' => $name,
            'status' => WorkspaceProfileStatus::Active,
            'enabled_nav_groups' => $enabledNav,
        ]);
    }

    public function test_member_sidebar_respects_department_enabled_nav_groups(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', ['overview', 'daily']);
        $member = $this->memberWithDept('HCNS', 'Hành Chính Nhân Sự');

        $keys = array_column(Navigation::for($member), 'key');

        $this->assertContains('overview', $keys);
        $this->assertContains('daily', $keys);
        $this->assertNotContains('projects', $keys);
        $this->assertNotContains('ai', $keys);
        $this->assertNotContains('contracts', $keys);
    }

    public function test_super_admin_bypasses_department_enabled_nav_groups(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', ['overview']);

        $employee = Employee::factory()->create([
            'meta' => [
                'department_code' => 'HCNS',
                'department_name' => 'Hành Chính Nhân Sự',
            ],
        ]);
        $super = SystemAccount::factory()
            ->role(SystemRole::SuperAdmin)
            ->forEmployee($employee)
            ->create();

        $keys = array_column(Navigation::for($super), 'key');

        $this->assertContains('overview', $keys);
        $this->assertContains('projects', $keys);
        $this->assertContains('daily', $keys);
    }

    public function test_null_enabled_nav_groups_does_not_restrict_member(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', null);
        $member = $this->memberWithDept('HCNS', 'Hành Chính Nhân Sự');

        $keys = array_column(Navigation::for($member), 'key');

        $this->assertContains('overview', $keys);
        $this->assertContains('projects', $keys);
        $this->assertContains('daily', $keys);
    }

    public function test_global_hidden_daily_hides_scoring_module_in_catalog(): void
    {
        config(['va.menu_hidden_groups' => ['daily']]);
        $super = $this->superAdmin();

        $keys = array_column(WorkspaceConfigCatalog::forUser($super), 'key');

        $this->assertNotContains('daily_report_scoring', $keys);
        $this->assertContains('evaluation', $keys);
    }

    public function test_global_hidden_performance_hides_evaluation_modules(): void
    {
        config(['va.menu_hidden_groups' => ['performance']]);
        $super = $this->superAdmin();

        $keys = array_column(WorkspaceConfigCatalog::forUser($super), 'key');

        $this->assertNotContains('evaluation', $keys);
        $this->assertNotContains('evaluation_templates', $keys);
        $this->assertNotContains('evaluation_forms', $keys);
        $this->assertContains('daily_report_scoring', $keys);
    }

    public function test_member_catalog_respects_department_enabled_nav_groups(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', ['overview', 'daily']);
        $member = $this->memberWithDept('HCNS', 'Hành Chính Nhân Sự');

        $keys = array_column(WorkspaceConfigCatalog::forUser($member, 'HCNS'), 'key');

        $this->assertContains('daily_report_scoring', $keys);
        $this->assertNotContains('evaluation', $keys);
        $this->assertNotContains('evaluation_templates', $keys);
    }

    public function test_super_admin_can_patch_enabled_nav_groups(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', null);

        $this->actingAs($this->superAdmin(), 'system')
            ->patch('/workspace-config/w/HCNS', [
                'enabled_nav_groups' => ['overview', 'daily', 'projects'],
            ])
            ->assertRedirect();

        $profile = WorkspaceProfile::query()->where('department_code', 'HCNS')->first();
        $this->assertSame(['overview', 'daily', 'projects'], $profile?->enabled_nav_groups);
    }

    public function test_super_admin_can_clear_enabled_nav_groups_to_null(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', ['overview']);

        $this->actingAs($this->superAdmin(), 'system')
            ->patch('/workspace-config/w/HCNS', [
                'enabled_nav_groups' => null,
            ])
            ->assertRedirect();

        $profile = WorkspaceProfile::query()->where('department_code', 'HCNS')->first();
        $this->assertNull($profile?->enabled_nav_groups);
    }

    public function test_patch_rejects_invalid_nav_group_key(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', null);

        $this->actingAs($this->superAdmin(), 'system')
            ->patch('/workspace-config/w/HCNS', [
                'enabled_nav_groups' => ['overview', 'not_a_real_group'],
            ])
            ->assertSessionHasErrors('enabled_nav_groups.1');
    }

    public function test_member_cannot_patch_enabled_nav_groups(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', null);
        $member = $this->memberWithDept('HCNS', 'Hành Chính Nhân Sự');

        $this->actingAs($member, 'system')
            ->patch('/workspace-config/w/HCNS', [
                'enabled_nav_groups' => ['overview'],
            ])
            ->assertForbidden();
    }

    public function test_show_includes_nav_menu_props_for_manager(): void
    {
        $this->seedDepartment('HCNS', 'Hành Chính Nhân Sự');
        app(HrmDepartmentDirectory::class)->forget();
        $this->ensureProfile('HCNS', 'Hành Chính Nhân Sự', ['overview', 'daily']);

        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config/w/HCNS')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Workspace/Show')
                ->has('navMenu.groups')
                ->where('workspace.enabled_nav_groups', ['overview', 'daily'])
                ->where('navMenu.enabled', ['overview', 'daily'])
            );
    }

    public function test_nav_module_map_helpers(): void
    {
        $this->assertSame('daily', WorkspaceNavModuleMap::navGroupForModule('daily_report_scoring'));
        $this->assertSame('performance', WorkspaceNavModuleMap::navGroupForModule('evaluation'));
        $this->assertNull(WorkspaceNavModuleMap::navGroupForModule('notifications'));

        config(['va.menu_hidden_groups' => ['daily']]);
        $this->assertFalse(WorkspaceNavModuleMap::isModuleVisible('daily_report_scoring'));
        $this->assertTrue(WorkspaceNavModuleMap::isModuleVisible('evaluation'));
        $this->assertFalse(WorkspaceNavModuleMap::isModuleVisible('evaluation', ['overview', 'daily']));
    }
}
