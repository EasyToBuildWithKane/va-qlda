<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_sprint_with_dates(): void
    {
        $project = Project::factory()->create();
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/sprints", [
                'name' => 'Sprint 1',
                'goal' => 'Goal',
                'status' => 'planned',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-14',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sprints', [
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_create_sprint_without_dates(): void
    {
        $project = Project::factory()->create();
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/sprints", [
                'name' => 'Sprint backlog',
                'goal' => '',
                'status' => 'planned',
                'start_date' => '',
                'end_date' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_manager_member_can_create_sprint_and_notifications_do_not_fail(): void
    {
        $project = Project::factory()->create();
        $manager = SystemAccount::factory()->role(SystemRole::Member)->create();
        $project->update(['manager_id' => $manager->employee_id]);
        $project->members()->attach($manager->employee_id, [
            'role' => 'developer',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($manager, 'system')
            ->post("/projects/{$project->id}/sprints", [
                'name' => 'Sprint M1',
                'goal' => null,
                'status' => 'planned',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-07',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_long_sprint_name_does_not_break_notification_insert(): void
    {
        $project = Project::factory()->create();
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create([
            'display_name' => str_repeat('A', 120),
        ]);
        $longName = str_repeat('S', 255);

        $this->actingAs($admin, 'system')
            ->post("/projects/{$project->id}/sprints", [
                'name' => $longName,
                'goal' => null,
                'status' => 'planned',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-07',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sprints', ['project_id' => $project->id, 'name' => $longName]);
    }
}
