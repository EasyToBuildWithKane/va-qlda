<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Database\Seeders\VaDieuVanTimelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaDieuVanTimelineSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_project_sprints_and_tasks(): void
    {
        $pm = Employee::factory()->create([
            'email' => 'kieunlt@hcm.vaschools.edu.vn',
            'full_name' => 'Nguyễn Lê Thanh Kiều',
        ]);
        Employee::factory()->create([
            'email' => 'khoana@hcm.vaschools.edu.vn',
            'full_name' => 'Khoa NA',
        ]);

        $this->seed(VaDieuVanTimelineSeeder::class);

        $project = Project::where('code', 'VA-DV')->firstOrFail();
        $this->assertSame($pm->id, $project->manager_id);
        $this->assertSame(5, Sprint::where('project_id', $project->id)->count());
        $this->assertGreaterThan(40, Task::where('project_id', $project->id)->count());
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'assignee_id' => Employee::where('email', 'khoana@hcm.vaschools.edu.vn')->value('id'),
        ]);
    }
}
