<?php

namespace Tests\Unit\Evaluation;

use App\Models\Employee;
use App\Support\Evaluation\HrmPositionDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HrmPositionDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_builds_distinct_positions_from_employee_fields(): void
    {
        Employee::factory()->create([
            'is_active' => true,
            'role_title' => 'Developer',
            'meta' => ['job_title_name' => 'Developer'],
        ]);
        Employee::factory()->create([
            'is_active' => true,
            'role_title' => 'QA Engineer',
            'meta' => [],
        ]);
        Employee::factory()->create([
            'is_active' => false,
            'role_title' => 'Should Ignore',
            'meta' => [],
        ]);

        $dir = app(HrmPositionDirectory::class);
        $dir->forget();
        $list = $dir->all(true);

        $names = collect($list)->pluck('name')->all();
        $this->assertContains('Developer', $names);
        $this->assertContains('QA Engineer', $names);
        $this->assertNotContains('Should Ignore', $names);

        $found = $dir->findByName('Developer');
        $this->assertNotNull($found);
        $this->assertSame($found['code'], HrmPositionDirectory::codeFromName('Developer'));
    }
}
