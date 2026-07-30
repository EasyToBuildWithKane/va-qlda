<?php

namespace Tests\Unit\Evaluation;

use App\Models\Department;
use App\Models\Employee;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\Evaluation\HrmDepartmentDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HrmDepartmentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_merges_local_and_employee_meta_departments(): void
    {
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
        $this->assertSame('hrm', $cntt['source']);
        $this->assertSame('Công Nghệ Thông Tin', $cntt['name']);
    }

    public function test_criterion_scope_labels(): void
    {
        $this->assertSame('Tiêu chí chung', EvaluationCriterionScope::General->label());
        $this->assertSame('Theo phòng ban', EvaluationCriterionScope::Department->label());
        $this->assertSame(['general', 'department'], EvaluationCriterionScope::values());
    }
}
