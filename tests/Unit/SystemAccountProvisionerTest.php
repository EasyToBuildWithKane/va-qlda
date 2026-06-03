<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Cms\CmsEmployeeSyncService;
use App\Services\Cms\SystemAccountProvisioner;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAccountProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_login_account_for_cms_employee(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'user@vaschools.edu.vn',
            'cms_user_id' => 501,
        ]);

        $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);

        $this->assertSame($employee->id, $account->employee_id);
        $this->assertSame('user@vaschools.edu.vn', $account->username);
        $this->assertSame(SystemRole::Member, $account->role);
        $this->assertTrue($account->is_active);
    }

    public function test_provision_missing_accounts_after_sync(): void
    {
        Employee::factory()->create([
            'email' => 'a@vaschools.edu.vn',
            'cms_user_id' => 1,
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'email' => 'b@vaschools.edu.vn',
            'cms_user_id' => 2,
            'is_active' => false,
        ]);

        $count = app(CmsEmployeeSyncService::class)->provisionMissingLoginAccounts();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('system_accounts', [
            'username' => 'a@vaschools.edu.vn',
        ]);
        $this->assertDatabaseMissing('system_accounts', [
            'username' => 'b@vaschools.edu.vn',
        ]);
    }

    public function test_reuses_existing_account_for_same_employee(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'x@vaschools.edu.vn',
            'cms_user_id' => 9,
        ]);

        SystemAccount::factory()->forEmployee($employee)->create([
            'username' => 'legacy-user',
        ]);

        $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);

        $this->assertSame('legacy-user', $account->username);
        $this->assertSame(1, SystemAccount::query()->where('employee_id', $employee->id)->count());
    }
}
