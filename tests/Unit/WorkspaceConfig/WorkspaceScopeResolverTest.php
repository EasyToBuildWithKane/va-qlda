<?php

namespace Tests\Unit\WorkspaceConfig;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Evaluation\HrmDepartmentDirectory;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceScopeResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'hrm.api.base_url' => '',
            'hrm.api.token' => '',
            'hrm.api.verify_ssl' => false,
        ]);
    }

    public function test_resolves_from_meta_department_code(): void
    {
        $this->seedHcns();
        $account = $this->accountWithMeta([
            'department_code' => 'HCNS',
            'department_name' => 'Hành Chính Nhân Sự',
        ]);

        $own = app(WorkspaceScopeResolver::class)->ownDepartment($account);

        $this->assertSame('HCNS', $own['code'] ?? null);
        $this->assertSame('Hành Chính Nhân Sự', $own['name'] ?? null);
    }

    public function test_resolves_from_department_member_pivot(): void
    {
        $dept = $this->seedHcns();
        $employee = Employee::factory()->create(['meta' => null]);
        $employee->departments()->attach($dept->id, [
            'is_active' => true,
            'joined_at' => now()->toDateString(),
        ]);
        $account = SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();

        $own = app(WorkspaceScopeResolver::class)->ownDepartment($account);

        $this->assertSame('HCNS', $own['code'] ?? null);
        $this->assertSame('Hành Chính Nhân Sự', $own['name'] ?? null);
    }

    public function test_resolves_from_department_name_without_code(): void
    {
        $this->seedHcns();
        $account = $this->accountWithMeta([
            'department_name' => 'Phòng Hành Chính Nhân Sự',
        ]);

        $own = app(WorkspaceScopeResolver::class)->ownDepartment($account);

        $this->assertSame('HCNS', $own['code'] ?? null);
        $this->assertSame('Hành Chính Nhân Sự', $own['name'] ?? null);
    }

    public function test_resolves_unit_to_parent_department(): void
    {
        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
        ]);
        $this->seedHcns();
        Http::fake(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/org-units')) {
                return Http::response(['data' => null], 404);
            }

            $type = $request['type'] ?? null;
            if ($type === 'unit') {
                return Http::response([
                    'data' => [[
                        'uuid' => '33333333-3333-3333-3333-333333333333',
                        'code' => 'HCNS-TN',
                        'name' => 'Tổ tuyển dụng',
                        'type' => 'unit',
                        'status' => 'active',
                        'parent' => ['code' => 'HCNS', 'name' => 'Hành Chính Nhân Sự'],
                    ]],
                    'meta' => ['cursor' => ['next' => null, 'count' => 1, 'per_page' => 100]],
                ]);
            }

            return Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'count' => 0, 'per_page' => 100]],
            ]);
        });
        app(HrmDepartmentDirectory::class)->forget();

        $account = $this->accountWithMeta([
            'unit_code' => 'HCNS-TN',
            'unit_name' => 'Tổ tuyển dụng',
        ]);

        $own = app(WorkspaceScopeResolver::class)->ownDepartment($account);

        $this->assertSame('HCNS', $own['code'] ?? null);
        $this->assertSame('Hành Chính Nhân Sự', $own['name'] ?? null);
    }

    public function test_keeps_name_from_hrm_meta_when_catalog_has_no_match(): void
    {
        $account = $this->accountWithMeta([
            'department_name' => 'Phòng chưa có trong danh mục',
        ]);

        $own = app(WorkspaceScopeResolver::class)->ownDepartment($account);

        $this->assertNotNull($own);
        $this->assertSame('Phòng chưa có trong danh mục', $own['name'] ?? null);
        $this->assertNotSame('', trim((string) ($own['code'] ?? '')));
    }

    private function seedHcns(): Department
    {
        $dept = Department::query()->create([
            'code' => 'HCNS',
            'name' => 'Hành Chính Nhân Sự',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        app(HrmDepartmentDirectory::class)->forget();

        return $dept;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function accountWithMeta(array $meta): SystemAccount
    {
        $employee = Employee::factory()->create(['meta' => $meta]);

        return SystemAccount::factory()
            ->role(SystemRole::Member)
            ->forEmployee($employee)
            ->create();
    }
}
