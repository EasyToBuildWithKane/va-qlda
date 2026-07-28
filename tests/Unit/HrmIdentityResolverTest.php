<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Hrm\HrmUser;
use App\Models\Hrm\HrmUserInfo;
use App\Services\Hrm\HrmIdentityResolver;
use App\Support\Hrm\HrmEmployeeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrmIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_maps_hrm_user_and_info_to_employee_fields(): void
    {
        $user = new HrmUser;
        $user->forceFill([
            'id' => 42,
            'name' => 'Nguyễn Văn A',
            'email' => 'A@Example.COM',
            'avatar' => 'https://cdn.example/avatar.jpg',
        ]);

        $info = new HrmUserInfo;
        $info->forceFill([
            'code' => 'NV001',
            'phone' => '0901234567',
            'position_name' => 'Developer',
            'department_name' => 'IT',
            'company_name' => 'VAS',
            'department_id' => 10,
            'company_id' => 2,
            'start_working_date' => Carbon::parse('2020-05-01'),
        ]);

        $attrs = HrmEmployeeMapper::toEmployeeAttributes($user, $info);

        $this->assertSame(42, $attrs['hrm_user_id']);
        $this->assertSame('NV001', $attrs['code']);
        $this->assertSame('Nguyễn Văn A', $attrs['full_name']);
        $this->assertSame('a@example.com', $attrs['email']);
        $this->assertSame('https://cdn.example/avatar.jpg', $attrs['avatar_path']);
        $this->assertSame('Developer', $attrs['role_title']);
        $this->assertSame('2020-05-01', $attrs['join_date']);
        $this->assertTrue($attrs['is_active']);
        $this->assertSame('IT', $attrs['meta']['department_name']);
    }

    public function test_mapper_uses_generated_code_when_hr_code_missing(): void
    {
        $user = $this->hrmUser(7, 'B', 'b@test.com');

        $attrs = HrmEmployeeMapper::toEmployeeAttributes($user, null);

        $this->assertSame('HRM-000007', $attrs['code']);
        $this->assertNull($attrs['meta']);
    }

    public function test_upsert_creates_employee_from_hrm_user(): void
    {
        $user = $this->hrmUser(100, 'Sync User', 'sync@vaschools.edu.vn');

        $resolver = new HrmIdentityResolver;
        $result = $resolver->upsertFromHrmUser($user);

        $this->assertSame('created', $result);
        $this->assertDatabaseHas('employees', [
            'hrm_user_id' => 100,
            'email' => 'sync@vaschools.edu.vn',
            'full_name' => 'Sync User',
        ]);
    }

    public function test_ensure_employee_from_hrm_returns_linked_employee(): void
    {
        $user = $this->hrmUser(150, 'Lazy User', 'lazy@vaschools.edu.vn');

        $resolver = new HrmIdentityResolver;
        $employee = $resolver->ensureEmployeeFromHrm($user);

        $this->assertSame(150, $employee->hrm_user_id);
        $this->assertSame('lazy@vaschools.edu.vn', $employee->email);
        $this->assertTrue($employee->is_active);
    }

    public function test_upsert_links_existing_employee_by_email_and_sets_hrm_user_id(): void
    {
        Employee::factory()->create([
            'code' => 'EMP-LEGACY',
            'email' => 'legacy@vaschools.edu.vn',
            'full_name' => 'Legacy Name',
        ]);

        $user = $this->hrmUser(200, 'HRM Name', 'legacy@vaschools.edu.vn');

        $resolver = new HrmIdentityResolver;
        $result = $resolver->upsertFromHrmUser($user);

        $this->assertSame('updated', $result);
        $this->assertDatabaseHas('employees', [
            'hrm_user_id' => 200,
            'email' => 'legacy@vaschools.edu.vn',
            'code' => 'EMP-LEGACY',
            'full_name' => 'HRM Name',
        ]);
    }

    public function test_upsert_marks_inactive_when_hrm_user_soft_deleted(): void
    {
        $user = $this->hrmUser(300, 'Left', 'left@vaschools.edu.vn');
        $user->deleted_at = now();

        $resolver = new HrmIdentityResolver;
        $resolver->upsertFromHrmUser($user);

        $this->assertDatabaseHas('employees', [
            'hrm_user_id' => 300,
            'is_active' => false,
        ]);
    }

    private function hrmUser(int $id, string $name, string $email): HrmUser
    {
        $user = new HrmUser;
        $user->forceFill([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        return $user;
    }
}
