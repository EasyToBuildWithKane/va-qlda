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
                'company' => ['name' => 'VAS'],
                'org_unit' => ['name' => 'CNTT'],
                'position' => ['name' => 'Dev'],
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
        $this->assertSame('VAS', $attrs['meta']['company_name']);
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
