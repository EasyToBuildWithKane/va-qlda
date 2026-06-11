<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Support\Enums\SprintStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintReorderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_admin_can_reorder_sprints(): void
    {
        $project = Project::factory()->create();
        $first = Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint A',
            'status' => SprintStatus::Planned,
            'sort_order' => 1,
        ]);
        $second = Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint B',
            'status' => SprintStatus::Planned,
            'sort_order' => 2,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/sprints/reorder", [
                'ids' => [$second->id, $first->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sprints', ['id' => $second->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('sprints', ['id' => $first->id, 'sort_order' => 2]);
    }

    public function test_reorder_requires_full_sprint_list(): void
    {
        $project = Project::factory()->create();
        $first = Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint A',
            'status' => SprintStatus::Planned,
            'sort_order' => 1,
        ]);
        Sprint::create([
            'project_id' => $project->id,
            'name' => 'Sprint B',
            'status' => SprintStatus::Planned,
            'sort_order' => 2,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/sprints/reorder", [
                'ids' => [$first->id],
            ])
            ->assertSessionHasErrors('ids');
    }
}
