<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
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

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Task',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
        ], $overrides);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Test Task',
        ]);
    }

    public function test_lead_can_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->lead(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload(['title' => 'Lead Task']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => 'Lead Task']);
    }

    public function test_member_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertForbidden();
    }

    public function test_task_requires_title(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post("/projects/{$project->id}/tasks", [
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::Medium->value,
            ])
            ->assertSessionHasErrors('title');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload(['title' => 'Old Title']));

        $this->actingAs($this->admin(), 'system')
            ->put("/projects/{$project->id}/tasks/{$task->id}", $this->taskPayload(['title' => 'New Title']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'New Title']);
    }

    public function test_member_cannot_update_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->member(), 'system')
            ->put("/projects/{$project->id}/tasks/{$task->id}", $this->taskPayload(['title' => 'Hacked']))
            ->assertForbidden();
    }

    // ─── Status patch ─────────────────────────────────────────────────────────

    public function test_admin_can_patch_task_status(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->admin(), 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", [
                'status' => TaskStatus::Done->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::Done->value]);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->admin(), 'system')
            ->delete("/projects/{$project->id}/tasks/{$task->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_viewer_cannot_delete_task(): void
    {
        $project = Project::factory()->create();
        $task = $project->tasks()->create($this->taskPayload());

        $this->actingAs($this->viewer(), 'system')
            ->delete("/projects/{$project->id}/tasks/{$task->id}")
            ->assertForbidden();
    }

    // ─── Guest ────────────────────────────────────────────────────────────────

    public function test_guest_cannot_create_task(): void
    {
        $project = Project::factory()->create();

        $this->post("/projects/{$project->id}/tasks", $this->taskPayload())
            ->assertRedirect('/login');
    }
}
