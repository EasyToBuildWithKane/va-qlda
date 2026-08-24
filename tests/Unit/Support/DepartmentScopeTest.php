<?php

namespace Tests\Unit\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Department\DepartmentScope;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ids_for_account_resolves_from_meta_department_code(): void
    {
        $dept = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $account = $this->accountWithMeta(['department_code' => 'PCN'], SystemRole::TeamLeader);

        $this->assertSame([$dept->id], DepartmentScope::idsForAccount($account));
    }

    public function test_ids_for_account_resolves_from_pivot(): void
    {
        $dept = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $employee = Employee::factory()->create(['meta' => null]);
        $employee->departments()->attach($dept->id, ['is_active' => true, 'joined_at' => now()->toDateString()]);
        $account = SystemAccount::factory()->role(SystemRole::TeamLeader)->forEmployee($employee)->create();

        $this->assertSame([$dept->id], DepartmentScope::idsForAccount($account));
    }

    public function test_ids_for_account_ignores_inactive_pivot_membership(): void
    {
        $dept = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $employee = Employee::factory()->create(['meta' => null]);
        $employee->departments()->attach($dept->id, ['is_active' => false, 'joined_at' => now()->toDateString()]);
        $account = SystemAccount::factory()->role(SystemRole::TeamLeader)->forEmployee($employee)->create();

        $this->assertSame([], DepartmentScope::idsForAccount($account));
    }

    public function test_ids_for_account_returns_empty_without_employee(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::TeamLeader)->create(['employee_id' => null]);

        $this->assertSame([], DepartmentScope::idsForAccount($account));
    }

    public function test_has_department_wide_scope_true_for_managerial_roles(): void
    {
        foreach ([SystemRole::Manager, SystemRole::DeputyManager, SystemRole::TeamLeader] as $role) {
            $account = SystemAccount::factory()->role($role)->create();
            $this->assertTrue(DepartmentScope::hasDepartmentWideScope($account), "role {$role->value} should have department-wide scope");
        }
    }

    public function test_has_department_wide_scope_true_for_admin_tier_without_the_permission(): void
    {
        config(['va_permissions.role_grants.admin' => []]);
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->assertTrue(DepartmentScope::hasDepartmentWideScope($admin));
    }

    public function test_has_department_wide_scope_false_for_member_and_viewer(): void
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();
        $viewer = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->assertFalse(DepartmentScope::hasDepartmentWideScope($member));
        $this->assertFalse(DepartmentScope::hasDepartmentWideScope($viewer));
    }

    public function test_employee_ids_in_own_department_merges_meta_and_pivot_matches(): void
    {
        $dept = $this->makeDepartment('PCN', 'Phòng Công nghệ');

        $manager = $this->accountWithMeta(['department_code' => 'PCN'], SystemRole::TeamLeader);

        $viaMeta = Employee::factory()->create(['is_active' => true, 'meta' => ['department_code' => 'PCN']]);
        $viaPivot = Employee::factory()->create(['is_active' => true, 'meta' => null]);
        $dept->members()->attach($viaPivot->id, ['is_active' => true, 'joined_at' => now()->toDateString()]);
        $unrelated = Employee::factory()->create(['is_active' => true, 'meta' => ['department_code' => 'KT']]);

        $ids = DepartmentScope::employeeIdsInOwnDepartment($manager);

        $this->assertContains($viaMeta->id, $ids);
        $this->assertContains($viaPivot->id, $ids);
        $this->assertNotContains($unrelated->id, $ids);
    }

    public function test_employee_ids_in_own_department_empty_when_department_unresolved(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::TeamLeader)->create(['employee_id' => null]);

        $this->assertSame([], DepartmentScope::employeeIdsInOwnDepartment($account));
    }

    private function makeDepartment(string $code, string $name): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $name,
            'color' => 'brand',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function accountWithMeta(array $meta, SystemRole $role): SystemAccount
    {
        $employee = Employee::factory()->create(['meta' => $meta]);

        return SystemAccount::factory()->role($role)->forEmployee($employee)->create();
    }
}
