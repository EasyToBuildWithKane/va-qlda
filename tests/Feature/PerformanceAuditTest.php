<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'hrm.api.base_url' => '',
            'hrm.api.token' => '',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-08-13 15:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_audit_index_lists_all_hrm_department_members_and_omits_midnight(): void
    {
        $lead = Employee::factory()->create([
            'full_name' => 'Trưởng phòng CNTT',
            'role_title' => 'Lead',
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
                'unit_name' => 'Ban lãnh đạo',
            ],
        ]);
        Employee::factory()->create([
            'full_name' => 'Dev Web A',
            'role_title' => 'Developer',
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
                'unit_name' => 'Tổ Web',
            ],
        ]);
        Employee::factory()->create([
            'full_name' => 'Dev Web B',
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
            ],
        ]);
        Employee::factory()->create([
            'full_name' => 'Nhân sự HCNS',
            'meta' => [
                'department_code' => 'HCNS',
                'department_name' => 'Hành chính nhân sự',
            ],
        ]);

        $account = SystemAccount::factory()->role(SystemRole::Admin)->forEmployee($lead)->create();

        $this->actingAs($account, 'system')
            ->get('/performance/audit?period=week&date=2026-08-13')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Performance/Audit')
                ->where('filter.department', 'CNTT')
                ->where('filter.department_name', 'Phòng Công nghệ')
                ->where('summary.total', 3)
                ->where('filter.label', fn ($label) => is_string($label) && ! str_contains($label, '00:00'))
                ->has('employees.data', 3)
                ->where('employees.data.0.departmentName', 'Phòng Công nghệ')
            );
    }

    public function test_member_cannot_view_audit(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get('/performance/audit')
            ->assertForbidden();
    }
}
