<?php

namespace Tests\Unit\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Performance\EmployeeOrgUnitResolver;
use App\Support\Performance\PerformanceDisplay;
use App\Support\Performance\PerformancePersonnelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformancePersonnelResolverTest extends TestCase
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
    }

    public function test_date_label_omits_midnight_clock(): void
    {
        $d = Carbon::parse('2026-08-13 00:00:00', 'Asia/Ho_Chi_Minh');

        $this->assertSame('13/08/2026', PerformanceDisplay::dateLabel($d));
        $this->assertSame('13/08/2026', PerformanceDisplay::dateAtMidnight($d));
        $this->assertStringNotContainsString('00:00', PerformanceDisplay::rangeLabel($d, $d->copy()->addDays(6)));
    }

    public function test_org_unit_label_prefers_hrm_meta_over_org_team(): void
    {
        $employee = Employee::factory()->create([
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
                'unit_name' => 'Tổ Web',
            ],
        ]);

        $team = OrgTeam::query()->create([
            'name' => 'Nhóm local lệch',
            'level' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        OrgTeamMember::query()->create([
            'org_team_id' => $team->id,
            'employee_id' => $employee->id,
            'sort_order' => 1,
        ]);

        $details = EmployeeOrgUnitResolver::detailsFor(collect([$employee->id]));

        $this->assertSame('Phòng Công nghệ · Tổ Web', $details[$employee->id]['label']);
        $this->assertSame('Phòng Công nghệ', $details[$employee->id]['department']);
        $this->assertSame('Tổ Web', $details[$employee->id]['unit']);
    }

    public function test_lists_all_employees_matching_hrm_department_code(): void
    {
        $viewer = Employee::factory()->create([
            'full_name' => 'Lead CNTT',
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
            ],
        ]);
        $sameDept = Employee::factory()->create([
            'full_name' => 'Dev cùng phòng',
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Phòng Công nghệ',
                'unit_name' => 'Tổ Web',
            ],
        ]);
        $byNameOnly = Employee::factory()->create([
            'full_name' => 'QA theo tên phòng',
            'meta' => [
                'department_name' => 'Phòng Công nghệ',
            ],
        ]);
        Employee::factory()->create([
            'full_name' => 'Nhân sự phòng khác',
            'meta' => [
                'department_code' => 'HCNS',
                'department_name' => 'Hành chính',
            ],
        ]);

        $account = SystemAccount::factory()->role(SystemRole::Admin)->forEmployee($viewer)->create();

        $resolver = app(PerformancePersonnelResolver::class);
        $dept = $resolver->resolveDepartment(null, $account);
        $this->assertSame('CNTT', $dept['code']);

        $ids = $resolver->employeeIds($dept['code'], $dept['all']);

        $this->assertTrue($ids->contains($viewer->id));
        $this->assertTrue($ids->contains($sameDept->id));
        $this->assertTrue($ids->contains($byNameOnly->id));
        $this->assertCount(3, $ids);
    }

    public function test_falls_back_to_tech_department_when_user_has_no_hrm_unit(): void
    {
        $tech = Department::query()->create([
            'code' => 'PCN',
            'name' => 'Phòng Công nghệ thông tin',
            'color' => 'sky',
            'is_active' => true,
        ]);
        $member = Employee::factory()->create(['full_name' => 'Tech pivot']);
        $tech->members()->attach($member->id, ['is_active' => true]);

        $orphan = Employee::factory()->create(['full_name' => 'Không phòng']);
        $account = SystemAccount::factory()->role(SystemRole::Admin)->forEmployee($orphan)->create();
        $this->actingAs($account, 'system');

        $resolved = app(PerformancePersonnelResolver::class)->resolveDepartment(null, $account);

        $this->assertFalse($resolved['all']);
        $this->assertSame('PCN', $resolved['code']);
        $this->assertSame($tech->id, $resolved['local_id']);
    }
}
