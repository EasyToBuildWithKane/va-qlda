<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Auth\BootstrapAdminRoleService;
use App\Support\Auth\Permissions;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapAdminRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'va_permissions.bootstrap_accounts' => [
                'admin.user@vaschools.edu.vn' => 'admin',
            ],
            'va_permissions.role_grants' => [
                'super_admin' => ['*'],
                'admin' => ['contract.manage', 'project.create'],
                'team_leader' => ['project.manage', 'contract.*'],
                'member' => ['daily_report.submit'],
            ],
        ]);
    }

    public function test_super_admin_has_wildcard(): void
    {
        $this->assertTrue(Permissions::roleAllows(SystemRole::SuperAdmin, 'notification.manage'));
        $this->assertTrue(Permissions::roleAllows(SystemRole::SuperAdmin, 'system.settings.manage'));
        $this->assertFalse(Permissions::roleAllows(SystemRole::Member, 'notification.manage'));
    }

    public function test_role_allows_respects_module_wildcard(): void
    {
        // 'contract.*' grants every contract ability …
        $this->assertTrue(Permissions::roleAllows(SystemRole::TeamLeader, 'contract.delete'));
        // … but does not leak into other modules.
        $this->assertFalse(Permissions::roleAllows(SystemRole::TeamLeader, 'vendor.delete'));
        // Exact grant only matches itself.
        $this->assertTrue(Permissions::roleAllows(SystemRole::Admin, 'project.create'));
        $this->assertFalse(Permissions::roleAllows(SystemRole::Admin, 'project.delete'));
    }

    public function test_bootstrap_promotes_existing_account_to_admin(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'admin.user@vaschools.edu.vn',
        ]);

        SystemAccount::factory()->forEmployee($employee)->create([
            'role' => SystemRole::Member,
        ]);

        $stats = app(BootstrapAdminRoleService::class)->applyBootstrapRoles(false);

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(SystemRole::Admin, $employee->fresh()->account->role);
    }

    public function test_bootstrap_resolves_employee_via_email_alias(): void
    {
        config([
            'va_permissions.bootstrap_accounts' => [
                'truchtm@vaschools.edu.vn' => 'admin',
            ],
            'va_permissions.bootstrap_email_aliases' => [
                'truchtm@vaschools.edu.vn' => ['truchtm@hcm.vaschools.edu.vn'],
            ],
        ]);

        $employee = Employee::factory()->create([
            'email' => 'truchtm@hcm.vaschools.edu.vn',
            'hrm_user_id' => 1,
        ]);

        SystemAccount::factory()->forEmployee($employee)->create([
            'role' => SystemRole::Member,
        ]);

        $stats = app(BootstrapAdminRoleService::class)->applyBootstrapRoles(false);

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(SystemRole::Admin, $employee->fresh()->account->role);
    }
}
