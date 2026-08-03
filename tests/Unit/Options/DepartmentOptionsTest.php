<?php

namespace Tests\Unit\Options;

use App\Models\Department;
use App\Support\Options\DepartmentOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepartmentOptionsTest extends TestCase
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
            'project.default_owner_department_code' => 'PCN',
        ]);
    }

    public function test_mirrors_hrm_org_units_into_local_departments_when_empty(): void
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
                            'code' => 'PCN',
                            'name' => 'Phòng Công nghệ',
                            'type' => 'department',
                            'status' => 'active',
                        ],
                        [
                            'uuid' => '22222222-2222-2222-2222-222222222222',
                            'code' => 'HCNS',
                            'name' => 'Phòng Hành chính Nhân sự',
                            'type' => 'department',
                            'status' => 'active',
                        ],
                    ],
                    'meta' => ['cursor' => ['next' => null, 'count' => 2, 'per_page' => 100]],
                ]);
            }

            return Http::response([
                'data' => [],
                'meta' => ['cursor' => ['next' => null, 'count' => 0, 'per_page' => 100]],
            ]);
        });

        $this->assertSame(0, Department::query()->count());

        $options = app(DepartmentOptions::class)->all();

        $this->assertGreaterThanOrEqual(2, $options->count());
        $this->assertTrue($options->contains(fn (array $d) => $d['code'] === 'PCN'));
        $this->assertTrue(Department::query()->where('code', 'PCN')->exists());

        $defaultId = app(DepartmentOptions::class)->defaultOwnerId();
        $pcnId = (int) Department::query()->where('code', 'PCN')->value('id');
        $this->assertSame($pcnId, $defaultId);
    }

    public function test_keeps_existing_local_departments_without_duplicating(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/org-units*' => Http::response([
                'data' => [
                    [
                        'uuid' => '11111111-1111-1111-1111-111111111111',
                        'code' => 'PCN',
                        'name' => 'Phòng Công nghệ (HRM)',
                        'type' => 'department',
                        'status' => 'active',
                    ],
                ],
                'meta' => ['cursor' => ['next' => null, 'count' => 1, 'per_page' => 100]],
            ]),
        ]);

        Department::query()->create([
            'code' => 'PCN',
            'name' => 'Phòng Công nghệ cũ',
            'color' => 'sky',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        app(DepartmentOptions::class)->flush();
        $options = app(DepartmentOptions::class)->all();

        $this->assertSame(1, Department::query()->where('code', 'PCN')->count());
        $this->assertTrue($options->contains(fn (array $d) => $d['code'] === 'PCN' && $d['name'] === 'Phòng Công nghệ (HRM)'));
    }
}
