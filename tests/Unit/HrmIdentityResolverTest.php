<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\Hrm\HrmApiClient;
use App\Services\Hrm\HrmIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrmIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
        ]);
    }

    public function test_ensure_employee_by_email_upserts_from_api(): void
    {
        $uuid = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        Http::fake([
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [[
                    'uuid' => $uuid,
                    'code' => 'NV009',
                    'full_name' => 'API User',
                    'status' => 'active',
                    'company_email' => 'api@vaschools.edu.vn',
                    'legacy_user_id' => 99,
                ]],
            ]),
        ]);

        $resolver = new HrmIdentityResolver(new HrmApiClient);
        $employee = $resolver->ensureEmployeeByEmail('api@vaschools.edu.vn');

        $this->assertNotNull($employee);
        $this->assertSame($uuid, $employee->hrm_employee_uuid);
        $this->assertSame(99, $employee->hrm_user_id);
        $this->assertSame('api@vaschools.edu.vn', $employee->email);
    }

    public function test_ensure_employee_by_email_returns_null_when_api_misses(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees*' => Http::response(['data' => []]),
        ]);

        $resolver = new HrmIdentityResolver(new HrmApiClient);

        $this->assertNull($resolver->ensureEmployeeByEmail('missing@vaschools.edu.vn'));
    }

    public function test_refresh_links_uuid_from_api_without_db_fallback(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $employee = Employee::factory()->create([
            'email' => 'user@vaschools.edu.vn',
            'full_name' => 'User A',
            'code' => 'NV001',
            'hrm_user_id' => null,
            'hrm_employee_uuid' => null,
        ]);

        Http::fake([
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [[
                    'uuid' => $uuid,
                    'code' => 'NV001',
                    'full_name' => 'User A',
                    'status' => 'active',
                    'company_email' => 'user@vaschools.edu.vn',
                    'legacy_user_id' => null,
                ]],
            ]),
        ]);

        $fresh = (new HrmIdentityResolver(new HrmApiClient))->refreshEmployeeIfLinked($employee);

        $this->assertSame($uuid, $fresh->fresh()->hrm_employee_uuid);
    }

    public function test_upsert_from_api_links_existing_employee_by_email(): void
    {
        Employee::factory()->create([
            'code' => 'EMP-LEGACY',
            'email' => 'legacy@vaschools.edu.vn',
            'full_name' => 'Legacy Name',
        ]);

        $resolver = new HrmIdentityResolver(new HrmApiClient);
        $result = $resolver->upsertFromApiEmployee([
            'uuid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'code' => 'NV200',
            'full_name' => 'HRM Name',
            'status' => 'active',
            'company_email' => 'legacy@vaschools.edu.vn',
            'legacy_user_id' => 200,
        ]);

        $this->assertSame('updated', $result);
        $this->assertDatabaseHas('employees', [
            'hrm_user_id' => 200,
            'hrm_employee_uuid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'email' => 'legacy@vaschools.edu.vn',
            'code' => 'EMP-LEGACY',
            'full_name' => 'HRM Name',
        ]);
    }

    public function test_upsert_marks_inactive_when_api_status_not_active(): void
    {
        $resolver = new HrmIdentityResolver(new HrmApiClient);
        $resolver->upsertFromApiEmployee([
            'uuid' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'code' => 'NV300',
            'full_name' => 'Left',
            'status' => 'terminated',
            'company_email' => 'left@vaschools.edu.vn',
            'legacy_user_id' => 300,
        ]);

        $this->assertDatabaseHas('employees', [
            'hrm_user_id' => 300,
            'is_active' => false,
        ]);
    }

    public function test_refresh_merges_hrm_meta_without_wiping_ql_fields(): void
    {
        $uuid = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $employee = Employee::factory()->create([
            'email' => 'merge@vaschools.edu.vn',
            'full_name' => 'Merge User',
            'code' => 'NV400',
            'hrm_employee_uuid' => $uuid,
            'meta' => [
                'bio' => 'Giới thiệu QLDA',
                'skill_details' => [['name' => 'Vue', 'level' => 4]],
                'department_name' => 'Cũ',
            ],
        ]);

        Http::fake([
            "https://hrm.test/api/v1/employees/{$uuid}" => Http::response([
                'data' => [
                    'uuid' => $uuid,
                    'code' => 'NV400',
                    'full_name' => 'Merge User',
                    'status' => 'active',
                    'company_email' => 'merge@vaschools.edu.vn',
                    'department_name' => 'Công nghệ',
                    'job_title_name' => 'Kỹ sư',
                    'primary_assignment' => [
                        'company' => ['code' => 'VAS', 'name' => 'VAS'],
                        'org_unit' => ['code' => 'IT', 'name' => 'CNTT', 'type' => 'department'],
                        'position' => ['title' => 'Kỹ sư'],
                    ],
                ],
            ]),
        ]);

        $fresh = (new HrmIdentityResolver(new HrmApiClient))->refreshEmployeeIfLinked($employee)->fresh();

        $this->assertSame('Giới thiệu QLDA', $fresh->meta['bio']);
        $this->assertSame(4, $fresh->meta['skill_details'][0]['level']);
        $this->assertSame('Công nghệ', $fresh->meta['department_name']);
        $this->assertSame('VAS', $fresh->meta['company_name']);
        $this->assertSame('Kỹ sư', $fresh->meta['position_name']);
    }

    public function test_is_hrm_configured_requires_api_token(): void
    {
        config(['hrm.api.token' => '']);

        $resolver = new HrmIdentityResolver(new HrmApiClient);
        $this->assertFalse($resolver->isHrmConfigured());
    }
}
