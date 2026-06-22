<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function test_admin_can_list_projects(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/projects')
            ->assertOk();
    }

    public function test_project_index_shows_all_projects_on_first_visit_without_per_page(): void
    {
        Project::factory()->count(12)->create();

        $this->actingAs($this->admin(), 'system')
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('projects.meta.per_page', 100)
                ->where('projects.meta.total', 12)
                ->has('projects.data', 12)
            );
    }

    public function test_project_index_merges_task_assignees_into_member_avatars(): void
    {
        $project = Project::factory()->create();
        $assignee = Employee::factory()->create();
        $project->tasks()->create([
            'title' => 'Assigned work',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->get('/projects')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_projects(): void
    {
        $this->get('/projects')->assertRedirect('/login');
    }

    // ─── Create / Store ───────────────────────────────────────────────────────

    public function test_admin_can_create_project(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'system')
            ->post('/projects', [
                'name' => 'Test Project',
                'code' => 'PRJ-TEST',
                'status' => ProjectStatus::Planning->value,
                'type' => ProjectType::Rnd->value,
                'scope' => ProjectScope::Headquarters->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Test Project']);
        $this->assertDatabaseMissing('projects', ['code' => 'PRJ-TEST']);
    }

    public function test_create_ignores_stale_suggested_code_and_allocates_unique(): void
    {
        Project::factory()->create(['code' => 'PRJ-001']);

        $this->actingAs($this->admin(), 'system')
            ->post('/projects', [
                'name' => 'Second Project',
                'code' => 'PRJ-001',
                'status' => ProjectStatus::Planning->value,
                'type' => ProjectType::Rnd->value,
                'scope' => ProjectScope::Headquarters->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Second Project', 'code' => 'PRJ-002']);
    }

    public function test_store_clears_stale_department_id_and_succeeds(): void
    {
        $dept = Department::create([
            'code' => 'PB-TEST',
            'name' => 'Phòng kiểm thử',
            'color' => 'brand',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->post('/projects', [
                'name' => 'Project With Stale Dept',
                'status' => ProjectStatus::Planning->value,
                'type' => ProjectType::Rnd->value,
                'scope' => ProjectScope::Headquarters->value,
                'department_id' => 999_999,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'name' => 'Project With Stale Dept',
            'department_id' => $dept->id,
        ]);
    }

    public function test_member_cannot_create_project(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/projects', [
                'name' => 'Unauthorized',
                'code' => 'PRJ-NO',
                'status' => ProjectStatus::Planning->value,
                'type' => ProjectType::Rnd->value,
                'scope' => ProjectScope::Headquarters->value,
            ])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_admin_can_view_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->get("/projects/{$project->id}")
            ->assertOk();
    }

    public function test_project_show_includes_merged_members_and_summary(): void
    {
        $project = Project::factory()->create();
        $assignee = Employee::factory()->create();
        $project->tasks()->create([
            'title' => 'Work item',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs($this->admin(), 'system')
            ->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.members', fn ($count) => $count >= 1)
                ->where('project.members', fn ($members) => collect($members)->contains('id', $assignee->id))
            );
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_project(): void
    {
        $project = Project::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin(), 'system')
            ->put("/projects/{$project->id}", [
                'code' => $project->code,
                'status' => $project->status->value,
                'type' => $project->type->value,
                'scope' => $project->scope->value,
                'name' => 'New Name',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name']);
    }

    public function test_viewer_cannot_update_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->put("/projects/{$project->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Duplicate ────────────────────────────────────────────────────────────

    public function test_admin_can_duplicate_project(): void
    {
        $project = Project::factory()->create(['name' => 'Original']);

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/duplicate")
            ->assertRedirect();

        $this->assertDatabaseHas('projects', ['name' => 'Original (bản sao)']);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->delete("/projects/{$project->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
