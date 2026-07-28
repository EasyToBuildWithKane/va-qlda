<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Hrm\SystemAccountProvisioner;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAccountProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_login_account_for_hrm_employee(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'user@vaschools.edu.vn',
            'hrm_user_id' => 501,
        ]);

        $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);

        $this->assertSame($employee->id, $account->employee_id);
        $this->assertSame('user@vaschools.edu.vn', $account->username);
        $this->assertSame(SystemRole::Member, $account->role);
        $this->assertTrue($account->is_active);
    }

    public function test_reuses_existing_account_for_same_employee(): void
    {
        $employee = Employee::factory()->create([
            'email' => 'x@vaschools.edu.vn',
            'hrm_user_id' => 9,
        ]);

        SystemAccount::factory()->forEmployee($employee)->create([
            'username' => 'legacy-user',
        ]);

        $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);

        $this->assertSame('legacy-user', $account->username);
        $this->assertSame(1, SystemAccount::query()->where('employee_id', $employee->id)->count());
    }
}
