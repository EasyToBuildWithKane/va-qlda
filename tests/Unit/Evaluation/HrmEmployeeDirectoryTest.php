<?php

namespace Tests\Unit\Evaluation;

use App\Models\Employee;
use App\Support\Evaluation\HrmEmployeeDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrmEmployeeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
            'hrm.api.timeout' => 5,
            'hrm.api.verify_ssl' => false,
        ]);

        app(HrmEmployeeDirectory::class)->forget();
    }

    public function test_syncs_active_employees_from_hrm_into_options(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [
                    [
                        'uuid' => '11111111-1111-1111-1111-111111111111',
                        'status' => 'active',
                        'full_name' => 'Nguyễn Văn A',
                        'company_email' => 'a@vaschools.edu.vn',
                        'code' => 'NV001',
                        'primary_assignment' => [
                            'org_unit' => ['code' => 'CNTT', 'name' => 'Công nghệ', 'type' => 'department'],
                        ],
                    ],
                    [
                        'uuid' => '22222222-2222-2222-2222-222222222222',
                        'status' => 'terminated',
                        'full_name' => 'Đã nghỉ',
                        'company_email' => 'gone@vaschools.edu.vn',
                        'code' => 'NV999',
                    ],
                ],
                'meta' => ['cursor' => ['next' => null]],
            ]),
        ]);

        $options = app(HrmEmployeeDirectory::class)->options(true);

        $this->assertTrue(
            collect($options)->contains(fn ($o) => $o['email'] === 'a@vaschools.edu.vn'),
        );
        $this->assertFalse(
            collect($options)->contains(fn ($o) => $o['email'] === 'gone@vaschools.edu.vn'),
        );
        $this->assertDatabaseHas('employees', [
            'email' => 'a@vaschools.edu.vn',
            'is_active' => true,
        ]);
    }

    public function test_falls_back_to_local_when_hrm_not_configured(): void
    {
        config(['hrm.api.token' => '']);

        Employee::factory()->create([
            'full_name' => 'Local User',
            'email' => 'local@vaschools.edu.vn',
            'is_active' => true,
        ]);

        $options = app(HrmEmployeeDirectory::class)->options(true);

        $this->assertTrue(
            collect($options)->contains(fn ($o) => $o['email'] === 'local@vaschools.edu.vn'),
        );
    }
}
