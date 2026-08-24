<?php

namespace Tests\Feature\Auth;

use App\Models\Contract;
use App\Models\Department;
use App\Models\SystemAccount;
use App\Models\SystemSetting;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The RBAC matrix actually drives policy authorization (edit/delete), and
 * super_admin is unconditionally all-powerful (Gate::before god-mode).
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function account(SystemRole $role): SystemAccount
    {
        return SystemAccount::factory()->role($role)->create();
    }

    public function test_matrix_grant_controls_delete_ability(): void
    {
        $lead = $this->account(SystemRole::TeamLeader);
        $contract = new Contract;

        // Grant contract.delete to team_leader → policy allows.
        config(['va_permissions.role_grants' => ['team_leader' => ['contract.delete']]]);
        $this->assertTrue(Gate::forUser($lead)->allows('delete', $contract));

        // Revoke it → policy denies.
        config(['va_permissions.role_grants' => ['team_leader' => []]]);
        $this->assertFalse(Gate::forUser($lead)->allows('delete', $contract));
    }

    public function test_module_wildcard_grants_every_ability(): void
    {
        $lead = $this->account(SystemRole::TeamLeader);
        config(['va_permissions.role_grants' => ['team_leader' => ['department.*']]]);

        $this->assertTrue(Gate::forUser($lead)->allows('create', Department::class));
        $this->assertTrue(Gate::forUser($lead)->allows('delete', new Department));
    }

    public function test_super_admin_is_all_powerful_even_with_empty_grants(): void
    {
        $super = $this->account(SystemRole::SuperAdmin);
        config(['va_permissions.role_grants' => ['super_admin' => []]]); // intentionally empty

        $this->assertTrue(Gate::forUser($super)->allows('delete', new Contract));
        $this->assertTrue(Gate::forUser($super)->allows('manage', SystemSetting::class));
        $this->assertTrue($super->allows('anything.at.all'));
    }

    public function test_admin_cannot_manage_system_settings(): void
    {
        $admin = $this->account(SystemRole::Admin);

        $this->assertFalse(Gate::forUser($admin)->allows('manage', SystemSetting::class));
        $this->assertFalse($admin->allows('system.settings.manage'));
    }
}
