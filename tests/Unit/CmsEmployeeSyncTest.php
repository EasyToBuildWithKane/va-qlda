<?php

namespace Tests\Unit;

use App\Models\Cms\CmsUser;
use App\Models\Cms\CmsUserInfo;
use App\Models\Employee;
use App\Services\Cms\CmsEmployeeSyncService;
use App\Support\Cms\CmsEmployeeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CmsEmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_maps_cms_user_and_info_to_employee_fields(): void
    {
        $user = new CmsUser;
        $user->forceFill([
            'id' => 42,
            'name' => 'Nguyễn Văn A',
            'email' => 'A@Example.COM',
            'avatar' => 'https://cdn.example/avatar.jpg',
        ]);

        $info = new CmsUserInfo;
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

        $attrs = CmsEmployeeMapper::toEmployeeAttributes($user, $info);

        $this->assertSame(42, $attrs['cms_user_id']);
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
        $user = $this->cmsUser(7, 'B', 'b@test.com');

        $attrs = CmsEmployeeMapper::toEmployeeAttributes($user, null);

        $this->assertSame('CMS-000007', $attrs['code']);
        $this->assertNull($attrs['meta']);
    }

    public function test_sync_creates_employee_from_cms_user(): void
    {
        $user = $this->cmsUser(100, 'Sync User', 'sync@vaschools.edu.vn');

        $service = new CmsEmployeeSyncService;
        $result = $service->upsertFromCmsUser($user);

        $this->assertSame('created', $result);
        $this->assertDatabaseHas('employees', [
            'cms_user_id' => 100,
            'email' => 'sync@vaschools.edu.vn',
            'full_name' => 'Sync User',
        ]);
    }

    public function test_sync_links_existing_employee_by_email_and_sets_cms_user_id(): void
    {
        Employee::factory()->create([
            'code' => 'EMP-LEGACY',
            'email' => 'legacy@vaschools.edu.vn',
            'full_name' => 'Legacy Name',
        ]);

        $user = $this->cmsUser(200, 'CMS Name', 'legacy@vaschools.edu.vn');

        $service = new CmsEmployeeSyncService;
        $result = $service->upsertFromCmsUser($user);

        $this->assertSame('updated', $result);
        $this->assertDatabaseHas('employees', [
            'cms_user_id' => 200,
            'email' => 'legacy@vaschools.edu.vn',
            'code' => 'EMP-LEGACY',
            'full_name' => 'CMS Name',
        ]);
    }

    public function test_sync_marks_inactive_when_cms_user_soft_deleted(): void
    {
        $user = $this->cmsUser(300, 'Left', 'left@vaschools.edu.vn');
        $user->deleted_at = now();

        $service = new CmsEmployeeSyncService;
        $service->upsertFromCmsUser($user);

        $this->assertDatabaseHas('employees', [
            'cms_user_id' => 300,
            'is_active' => false,
        ]);
    }

    private function cmsUser(int $id, string $name, string $email): CmsUser
    {
        $user = new CmsUser;
        $user->forceFill([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        return $user;
    }
}
