<?php

namespace Tests\Unit;

use App\Support\Hrm\HrmApiEmployeeMapper;
use PHPUnit\Framework\TestCase;

class HrmApiEmployeeMapperTest extends TestCase
{
    public function test_maps_api_employee_payload_to_ql_attributes(): void
    {
        $attrs = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'code' => 'NV001',
            'full_name' => 'Nguyễn Văn A',
            'status' => 'active',
            'company_email' => 'A@Example.COM',
            'personal_email' => 'a.personal@gmail.com',
            'phone' => '0901234567',
            'avatar_path' => 'https://cdn.example/a.jpg',
            'department_name' => 'IT',
            'job_title_name' => 'Developer',
            'job_position' => null,
            'hired_at' => '2020-05-01',
            'terminated_at' => null,
            'legacy_user_id' => 42,
            'primary_assignment' => [
                'company' => ['code' => 'VAS', 'name' => 'VAS'],
                'org_unit' => ['code' => 'CNTT', 'name' => 'CNTT', 'type' => 'department'],
                'position' => ['title' => 'Dev'],
            ],
            'concurrent_assignments' => [
                [
                    'position' => ['title' => 'Trưởng nhóm'],
                    'org_unit' => ['name' => 'QA'],
                ],
            ],
        ]);

        $this->assertSame('11111111-1111-1111-1111-111111111111', $attrs['hrm_employee_uuid']);
        $this->assertSame(42, $attrs['hrm_user_id']);
        $this->assertSame('NV001', $attrs['code']);
        $this->assertSame('Nguyễn Văn A', $attrs['full_name']);
        $this->assertSame('a@example.com', $attrs['email']);
        $this->assertSame('Developer', $attrs['role_title']);
        $this->assertSame('2020-05-01', $attrs['join_date']);
        $this->assertTrue($attrs['is_active']);
        $this->assertSame('IT', $attrs['meta']['department_name']);
        $this->assertSame('CNTT', $attrs['meta']['department_code']);
        $this->assertSame('VAS', $attrs['meta']['company_name']);
        $this->assertSame('VAS', $attrs['meta']['company_id']);
        $this->assertSame('Developer', $attrs['meta']['position_name']);
        $this->assertSame('Trưởng nhóm · QA', $attrs['meta']['concurrent_position_name']);
        $this->assertSame('https://cdn.example/a.jpg', $attrs['avatar_path']);
    }

    public function test_maps_unit_and_headquarter_from_org_type(): void
    {
        $unit = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'full_name' => 'C',
            'status' => 'active',
            'company_email' => 'c@vaschools.edu.vn',
            'primary_assignment' => [
                'company' => ['code' => 'VA', 'name' => 'VA Schools'],
                'org_unit' => ['code' => 'WEB', 'name' => 'Web', 'type' => 'unit'],
                'position' => ['title' => 'Member'],
            ],
        ]);

        $this->assertSame('Web', $unit['meta']['unit_name']);
        $this->assertSame('WEB', $unit['meta']['unit_code']);
        $this->assertSame('Member', $unit['meta']['position_name']);
        $this->assertArrayNotHasKey('department_name', $unit['meta']);
        $this->assertArrayNotHasKey('department_code', $unit['meta']);

        $hq = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'full_name' => 'D',
            'status' => 'active',
            'company_email' => 'd@vaschools.edu.vn',
            'primary_assignment' => [
                'org_unit' => ['name' => 'Hội sở', 'type' => 'headquarter'],
            ],
        ]);

        $this->assertSame('Hội sở', $hq['meta']['headquarter_name']);
    }

    public function test_maps_headquarter_from_assignment_ancestors_and_workplace(): void
    {
        $fromBranch = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'full_name' => 'E',
            'status' => 'active',
            'company_email' => 'e@vaschools.edu.vn',
            'workplace' => 'Cơ sở Cầu Giấy (fallback)',
            'primary_assignment' => [
                'org_unit' => ['name' => 'CNTT', 'type' => 'department'],
                'headquarter' => ['uuid' => 'h1', 'code' => 'HQ', 'name' => 'Văn phòng tổng'],
                'branch' => ['uuid' => 'b1', 'code' => 'CG', 'name' => 'Cơ sở Cầu Giấy'],
            ],
        ]);

        $this->assertSame('Cơ sở Cầu Giấy', $fromBranch['meta']['headquarter_name']);
        $this->assertSame('CNTT', $fromBranch['meta']['department_name']);
        $this->assertSame('Cơ sở Cầu Giấy (fallback)', $fromBranch['meta']['workplace']);

        $fromWorkplace = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '66666666-6666-6666-6666-666666666666',
            'full_name' => 'F',
            'status' => 'active',
            'company_email' => 'f@vaschools.edu.vn',
            'workplace' => 'Cơ sở Mỹ Đình',
            'primary_assignment' => [
                'org_unit' => ['name' => 'HCNS', 'type' => 'department'],
            ],
        ]);

        $this->assertSame('Cơ sở Mỹ Đình', $fromWorkplace['meta']['headquarter_name']);
    }

    public function test_maps_parent_department_when_assigned_to_unit(): void
    {
        $attrs = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '77777777-7777-7777-7777-777777777777',
            'full_name' => 'G',
            'status' => 'active',
            'company_email' => 'g@vaschools.edu.vn',
            'department_name' => 'Phòng Công nghệ',
            'primary_assignment' => [
                'department' => ['code' => 'CNTT', 'name' => 'Phòng Công nghệ'],
                'org_unit' => [
                    'code' => 'WEB',
                    'name' => 'Tổ Web',
                    'type' => 'unit',
                    'parent' => ['code' => 'CNTT', 'name' => 'Phòng Công nghệ', 'type' => 'department'],
                ],
                'position' => ['title' => 'Dev'],
            ],
        ]);

        $this->assertSame('CNTT', $attrs['meta']['department_code']);
        $this->assertSame('Phòng Công nghệ', $attrs['meta']['department_name']);
        $this->assertSame('WEB', $attrs['meta']['unit_code']);
        $this->assertSame('Tổ Web', $attrs['meta']['unit_name']);
    }

    public function test_falls_back_to_personal_email_and_inactive_status(): void
    {
        $attrs = HrmApiEmployeeMapper::toEmployeeAttributes([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'code' => null,
            'full_name' => 'B',
            'status' => 'terminated',
            'company_email' => null,
            'personal_email' => 'b@vaschools.edu.vn',
            'legacy_user_id' => null,
        ]);

        $this->assertSame('b@vaschools.edu.vn', $attrs['email']);
        $this->assertFalse($attrs['is_active']);
        $this->assertNull($attrs['hrm_user_id']);
        $this->assertStringStartsWith('HRM-', $attrs['code']);
    }
}
