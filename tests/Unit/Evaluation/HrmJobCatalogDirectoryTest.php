<?php

namespace Tests\Unit\Evaluation;

use App\Models\Employee;
use App\Support\Evaluation\HrmJobCatalogDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HrmJobCatalogDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_builds_titles_and_ranks_from_employee_meta(): void
    {
        Employee::factory()->create([
            'is_active' => true,
            'role_title' => 'Chuyên viên Kinh doanh',
            'meta' => [
                'rank_name' => 'M2',
                'job_level' => 'Senior',
            ],
        ]);

        $dir = app(HrmJobCatalogDirectory::class);
        $dir->forget();

        $titles = collect($dir->titles(true))->pluck('name')->all();
        $ranks = collect($dir->ranks(true))->pluck('name')->all();

        $this->assertContains('Chuyên viên Kinh doanh', $titles);
        $this->assertContains('M2', $ranks);
        $this->assertContains('Senior', $ranks);
    }

    public function test_extracts_named_rank_from_job_title_row_shape(): void
    {
        $dir = app(HrmJobCatalogDirectory::class);
        $method = new \ReflectionMethod($dir, 'extractRankFromJobTitleRow');
        $method->setAccessible(true);

        $named = $method->invoke($dir, [
            'name' => 'Chuyên viên',
            'level' => 2,
            'rank_name' => 'Trung cấp',
            'rank_code' => 'TC',
        ]);

        $this->assertIsArray($named);
        $this->assertSame('Trung cấp', $named['name']);
        $this->assertSame('TC', $named['code']);
    }

    public function test_normalizes_numeric_rank_names_to_cap_label(): void
    {
        Employee::factory()->create([
            'is_active' => true,
            'role_title' => 'Nhân viên',
            'meta' => [
                'job_level' => '3',
                'rank' => '1',
            ],
        ]);

        $dir = app(HrmJobCatalogDirectory::class);
        $ranks = collect($dir->ranks(true))->keyBy('code');

        $this->assertSame('Cấp 3', $ranks->get('L3')['name'] ?? null);
        $this->assertSame('Cấp 1', $ranks->get('L1')['name'] ?? null);
        $this->assertNotContains('1', $ranks->pluck('name')->all());
        $this->assertNotContains('3', $ranks->pluck('name')->all());
    }

    public function test_map_api_row_rank_prefixes_numeric_name(): void
    {
        $dir = app(HrmJobCatalogDirectory::class);
        $method = new \ReflectionMethod($dir, 'mapApiRow');
        $method->setAccessible(true);

        $mapped = $method->invoke($dir, [
            'name' => '2',
            'code' => '2',
            'level' => 2,
        ], 'RANK', 'hrm');

        $this->assertIsArray($mapped);
        $this->assertSame('Cấp 2', $mapped['name']);
        $this->assertSame('2', $mapped['code']);
    }
}
