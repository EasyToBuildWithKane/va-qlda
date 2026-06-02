<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\SystemRole;
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

        $this->assertDatabaseHas('projects', ['code' => 'PRJ-TEST', 'name' => 'Test Project']);
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
