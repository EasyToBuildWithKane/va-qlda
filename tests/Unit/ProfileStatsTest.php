<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\Profile\ProfileStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileStatsTest extends TestCase
{
    use RefreshDatabase;
    public function test_skill_score_from_skills_list_without_skill_details(): void
    {
        $employee = Employee::factory()->create([
            'skills' => ['Laravel', 'Vue'],
            'meta' => [],
        ]);

        $stats = ProfileStats::for($employee);

        $this->assertSame(60, $stats['skill_score']);
        $this->assertSame(2, $stats['rated_skills']);
    }

    public function test_skill_score_from_skill_details_levels(): void
    {
        $employee = Employee::factory()->create([
            'skills' => ['Laravel'],
            'meta' => [
                'skill_details' => [
                    ['name' => 'Laravel', 'level' => 4, 'category' => 'backend'],
                ],
            ],
        ]);

        $stats = ProfileStats::for($employee);

        $this->assertSame(80, $stats['skill_score']);
    }
}
