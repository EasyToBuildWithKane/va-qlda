<?php

namespace Tests\Feature;

use App\Models\Bug;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BugTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function lead(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Lead)->create();
    }

    private function member(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Member)->create();
    }

    private function viewer(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Viewer)->create();
    }

    private function bugPayload(int $projectId, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $projectId,
            'title' => 'Test Bug',
            'severity' => BugSeverity::Minor->value,
            'priority' => TaskPriority::Medium->value,
            'reporter_name' => 'Tester',
        ], $overrides);
    }

    private function createBug(Project $project, array $overrides = []): Bug
    {
        return Bug::create(array_merge([
            'project_id' => $project->id,
            'title' => 'Existing Bug',
            'severity' => BugSeverity::Major->value,
            'priority' => TaskPriority::High->value,
            'status' => BugStatus::Open->value,
            'reporter_name' => 'Tester',
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_bugs(): void
    {
        $this->actingAs($this->member(), 'system')
            ->get('/bugs')
            ->assertOk();
    }

    public function test_viewer_can_view_bugs(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->get('/bugs')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_bugs(): void
    {
        $this->get('/bugs')->assertRedirect('/login');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_bug(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/bugs', $this->bugPayload($project->id))
            ->assertRedirect();

        $this->assertDatabaseHas('bugs', [
            'project_id' => $project->id,
            'title' => 'Test Bug',
        ]);
    }

    public function test_admin_can_create_bug(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post('/bugs', $this->bugPayload($project->id, ['title' => 'Admin Bug']))
            ->assertRedirect();

        $this->assertDatabaseHas('bugs', ['title' => 'Admin Bug']);
    }

    public function test_viewer_cannot_create_bug(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post('/bugs', $this->bugPayload($project->id))
            ->assertForbidden();
    }

    public function test_bug_requires_title_severity_priority(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/bugs', ['project_id' => $project->id, 'reporter_name' => 'Tester'])
            ->assertSessionHasErrors(['title', 'severity', 'priority']);
    }

    public function test_store_redirects_to_bug_show(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->member(), 'system')
            ->post('/bugs', $this->bugPayload($project->id));

        $response->assertRedirect();
        $this->assertStringContainsString('/bugs/', $response->headers->get('Location'));
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_any_authenticated_user_can_view_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->viewer(), 'system')
            ->get("/bugs/{$bug->id}")
            ->assertOk();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->admin(), 'system')
            ->put("/bugs/{$bug->id}", [
                'title' => 'Updated Bug',
                'severity' => BugSeverity::Critical->value,
                'priority' => TaskPriority::High->value,
                'status' => BugStatus::Open->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bugs', ['id' => $bug->id, 'title' => 'Updated Bug']);
    }

    public function test_lead_can_update_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->lead(), 'system')
            ->put("/bugs/{$bug->id}", [
                'title' => 'Lead Updated',
                'severity' => BugSeverity::Minor->value,
                'priority' => TaskPriority::Low->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bugs', ['id' => $bug->id, 'title' => 'Lead Updated']);
    }

    public function test_unrelated_member_cannot_update_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->member(), 'system')
            ->put("/bugs/{$bug->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_resolving_bug_sets_resolved_at(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->admin(), 'system')
            ->put("/bugs/{$bug->id}", [
                'title' => $bug->title,
                'severity' => $bug->severity->value,
                'priority' => $bug->priority->value,
                'status' => BugStatus::Resolved->value,
            ]);

        $this->assertNotNull($bug->fresh()->resolved_at);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->admin(), 'system')
            ->delete("/bugs/{$bug->id}")
            ->assertRedirect(route('bugs.index'));

        $this->assertSoftDeleted('bugs', ['id' => $bug->id]);
    }

    public function test_lead_cannot_delete_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->lead(), 'system')
            ->delete("/bugs/{$bug->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_delete_bug(): void
    {
        $project = Project::factory()->create();
        $bug = $this->createBug($project);

        $this->actingAs($this->member(), 'system')
            ->delete("/bugs/{$bug->id}")
            ->assertForbidden();
    }
}
