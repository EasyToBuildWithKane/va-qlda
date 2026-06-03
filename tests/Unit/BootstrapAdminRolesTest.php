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
                'admin' => ['*'],
                'member' => ['daily_reports.submit'],
            ],
        ]);
    }

    public function test_permissions_admin_has_wildcard(): void
    {
        $this->assertTrue(Permissions::roleAllows(SystemRole::Admin, 'notifications.manage'));
        $this->assertFalse(Permissions::roleAllows(SystemRole::Member, 'notifications.manage'));
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

    public function test_provisioner_uses_bootstrap_role_on_create(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'admin.user@vaschools.edu.vn',
            'cms_user_id' => 99,
        ]);

        $account = app(\App\Services\Cms\SystemAccountProvisioner::class)->ensureForEmployee($employee);

        $this->assertSame(SystemRole::Admin, $account->role);
    }
}
