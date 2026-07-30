<?php

namespace Tests\Unit\Evaluation;

use App\Models\Department;
use App\Models\Employee;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Evaluation\HrmDepartmentDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrmDepartmentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
            'hrm.api.timeout' => 5,
            'hrm.api.verify_ssl' => false,
        ]);
    }

    public function test_merges_local_and_employee_meta_departments(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'prev' => null, 'count' => 0, 'per_page' => 100]],
            ]),
        ]);

        Department::query()->create([
            'code' => 'HCNS',
            'name' => 'Hành Chính Nhân Sự',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Employee::factory()->create([
            'is_active' => true,
            'meta' => [
                'department_code' => 'CNTT',
                'department_name' => 'Công Nghệ Thông Tin',
            ],
        ]);

        $list = app(HrmDepartmentDirectory::class)->all(true);

        $codes = collect($list)->pluck('code')->all();
        $this->assertContains('HCNS', $codes);
        $this->assertContains('CNTT', $codes);

        $hcns = collect($list)->firstWhere('code', 'HCNS');
        $this->assertSame('local', $hcns['source']);
        $this->assertNotNull($hcns['local_department_id']);

        $cntt = collect($list)->firstWhere('code', 'CNTT');
        $this->assertSame('employee', $cntt['source']);
        $this->assertSame('Công Nghệ Thông Tin', $cntt['name']);
    }

    public function test_loads_departments_from_hrm_org_units_api(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/org-units')) {
                return Http::response(['data' => null], 404);
            }

            $type = $request['type'] ?? null;
            if ($type === 'department') {
                return Http::response([
                    'data' => [
                        [
                            'uuid' => '11111111-1111-1111-1111-111111111111',
                            'code' => 'KT',
                            'name' => 'Phòng Kế toán',
                            'type' => 'department',
                            'status' => 'active',
                        ],
                        [
                            'uuid' => '22222222-2222-2222-2222-222222222222',
                            'code' => 'OLD',
                            'name' => 'Phòng cũ',
                            'type' => 'department',
                            'status' => 'inactive',
                        ],
                    ],
                    'meta' => ['cursor' => ['next' => null, 'count' => 2, 'per_page' => 100]],
                ]);
            }

            if ($type === 'unit') {
                return Http::response([
                    'data' => [
                        [
                            'uuid' => '33333333-3333-3333-3333-333333333333',
                            'code' => 'QA',
                            'name' => 'Tổ QA',
                            'type' => 'unit',
                            'status' => 'active',
                        ],
                    ],
                    'meta' => ['cursor' => ['next' => null, 'count' => 1, 'per_page' => 100]],
                ]);
            }

            return Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'count' => 0, 'per_page' => 100]],
            ]);
        });

        Department::query()->create([
            'code' => 'KT',
            'name' => 'Kế toán local',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $list = app(HrmDepartmentDirectory::class)->all(true);
        $byCode = collect($list)->keyBy('code');

        $this->assertTrue($byCode->has('KT'));
        $this->assertSame('hrm', $byCode['KT']['source']);
        $this->assertSame('Phòng Kế toán', $byCode['KT']['name']);
        $this->assertNotNull($byCode['KT']['local_department_id']);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $byCode['KT']['hrm_uuid']);

        $this->assertTrue($byCode->has('QA'));
        $this->assertSame('hrm', $byCode['QA']['source']);
        $this->assertFalse($byCode->has('OLD'));
    }

    public function test_falls_back_when_hrm_api_not_configured(): void
    {
        config(['hrm.api.token' => '']);
        Http::fake();

        Department::query()->create([
            'code' => 'LOCAL',
            'name' => 'Phòng local',
            'color' => 'slate',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $list = app(HrmDepartmentDirectory::class)->all(true);
        $this->assertSame(['LOCAL'], collect($list)->pluck('code')->all());
        Http::assertNothingSent();
    }

    public function test_criterion_scope_labels(): void
    {
        $this->assertSame('Tiêu chí chung', EvaluationCriterionScope::General->label());
        $this->assertSame('Theo phòng ban', EvaluationCriterionScope::Department->label());
        $this->assertSame(['general', 'department'], EvaluationCriterionScope::values());
    }
}
