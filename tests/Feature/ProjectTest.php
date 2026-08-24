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

    private function teamLeader(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::TeamLeader)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function makeDepartment(string $code, string $name): Department
    {
        return Department::create([
            'code' => $code,
            'name' => $name,
            'color' => 'brand',
            'sort_order' => 1,
            'is_active' => true,
        ]);
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

    public function test_store_saves_related_departments_excluding_owner(): void
    {
        $owner = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $partner = $this->makeDepartment('HV', 'Phòng Học vụ');
        $other = $this->makeDepartment('HCNS', 'Hành chính');

        $this->actingAs($this->admin(), 'system')
            ->post('/projects', [
                'name' => 'Dự án liên đới',
                'status' => ProjectStatus::Planning->value,
                'type' => ProjectType::Rnd->value,
                'scope' => ProjectScope::Headquarters->value,
                'department_id' => $owner->id,
                'scope_departments' => [$owner->id, $partner->id, $other->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project = Project::query()->where('name', 'Dự án liên đới')->first();
        $this->assertNotNull($project);
        $this->assertSame($owner->id, $project->department_id);
        $this->assertEqualsCanonicalizing([$partner->id, $other->id], $project->relatedDepartmentIds());
    }

    public function test_team_leader_in_related_department_can_view_project(): void
    {
        $owner = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $partner = $this->makeDepartment('HV', 'Phòng Học vụ');
        $outsider = $this->makeDepartment('KT', 'Kế toán');

        $project = Project::factory()->create([
            'department_id' => $owner->id,
            'scope_departments' => [$partner->id],
        ]);

        $relatedAccount = $this->teamLeader();
        $partner->members()->attach($relatedAccount->employee_id, ['is_active' => true]);

        $outsiderAccount = $this->teamLeader();
        $outsider->members()->attach($outsiderAccount->employee_id, ['is_active' => true]);

        $this->actingAs($relatedAccount, 'system')
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('projects.meta.total', 1));

        $this->actingAs($relatedAccount, 'system')
            ->get("/projects/{$project->id}")
            ->assertOk();

        $this->actingAs($outsiderAccount, 'system')
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('projects.meta.total', 0));

        $this->actingAs($outsiderAccount, 'system')
            ->get("/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_member_in_related_department_cannot_view_project(): void
    {
        // Option A (2026-08-24): member/viewer no longer inherit department-wide
        // audience visibility — only department.view_scope holders (manager/
        // deputy_manager/team_leader/admin) do. A plain member in the partner
        // department, with no manager/member relationship to the project, sees
        // nothing — this closes what was previously unintended over-exposure.
        $owner = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $partner = $this->makeDepartment('HV', 'Phòng Học vụ');

        $project = Project::factory()->create([
            'department_id' => $owner->id,
            'scope_departments' => [$partner->id],
        ]);

        $relatedAccount = $this->member();
        $partner->members()->attach($relatedAccount->employee_id, ['is_active' => true]);

        $this->actingAs($relatedAccount, 'system')
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('projects.meta.total', 0));

        $this->actingAs($relatedAccount, 'system')
            ->get("/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_owner_department_team_leader_can_view_without_being_project_member(): void
    {
        $owner = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $project = Project::factory()->create([
            'department_id' => $owner->id,
            'scope_departments' => [],
        ]);

        $account = $this->teamLeader();
        $owner->members()->attach($account->employee_id, ['is_active' => true]);

        $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}")
            ->assertOk();
    }

    public function test_owner_department_member_cannot_view_without_being_project_member(): void
    {
        // Option A: same department but not a manager/member of the project —
        // a plain member no longer gets department-wide visibility (unchanged
        // for team_leader/manager/deputy_manager/admin, see test above).
        $owner = $this->makeDepartment('PCN', 'Phòng Công nghệ');
        $project = Project::factory()->create([
            'department_id' => $owner->id,
            'scope_departments' => [],
        ]);

        $account = $this->member();
        $owner->members()->attach($account->employee_id, ['is_active' => true]);

        $this->actingAs($account, 'system')
            ->get("/projects/{$project->id}")
            ->assertForbidden();
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
