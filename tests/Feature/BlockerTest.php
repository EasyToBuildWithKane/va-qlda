<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockerTest extends TestCase
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

    private function blockerPayload(int $projectId, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $projectId,
            'title' => 'Vướng mắc thử nghiệm',
            'severity' => BlockerSeverity::Medium->value,
        ], $overrides);
    }

    private function createBlocker(Project $project, ?SystemAccount $raisedBy = null): Blocker
    {
        return Blocker::create([
            'project_id' => $project->id,
            'title' => 'Existing blocker',
            'severity' => BlockerSeverity::High->value,
            'status' => BlockerStatus::Open->value,
            'raised_by_id' => $raisedBy?->employee_id,
            'raised_at' => now(),
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_blockers(): void
    {
        $this->actingAs($this->member(), 'system')
            ->get('/blockers')
            ->assertOk();
    }

    public function test_viewer_can_view_blockers(): void
    {
        $this->actingAs($this->viewer(), 'system')
            ->get('/blockers')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_blockers(): void
    {
        $this->get('/blockers')->assertRedirect('/login');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_blocker(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/blockers', $this->blockerPayload($project->id))
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', [
            'project_id' => $project->id,
            'title' => 'Vướng mắc thử nghiệm',
        ]);
    }

    public function test_admin_can_create_blocker(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post('/blockers', $this->blockerPayload($project->id, ['title' => 'Admin blocker']))
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', ['title' => 'Admin blocker']);
    }

    public function test_viewer_cannot_create_blocker(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post('/blockers', $this->blockerPayload($project->id))
            ->assertForbidden();
    }

    public function test_blocker_requires_title_and_severity(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->member(), 'system')
            ->post('/blockers', ['project_id' => $project->id])
            ->assertSessionHasErrors(['title', 'severity']);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_blocker(): void
    {
        $project = Project::factory()->create();
        $admin = $this->admin();
        $blocker = $this->createBlocker($project, $admin);

        $this->actingAs($admin, 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => 'Updated title',
                'severity' => BlockerSeverity::Critical->value,
                'status' => BlockerStatus::Open->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', ['id' => $blocker->id, 'title' => 'Updated title']);
    }

    public function test_raiser_can_update_own_blocker(): void
    {
        $project = Project::factory()->create();
        $member = $this->member();
        $blocker = $this->createBlocker($project, $member);

        $this->actingAs($member, 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => 'Fixed by raiser',
                'severity' => BlockerSeverity::Low->value,
                'status' => BlockerStatus::Open->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', ['id' => $blocker->id, 'title' => 'Fixed by raiser']);
    }

    public function test_unrelated_member_cannot_update_blocker(): void
    {
        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);
        $other = $this->member();

        $this->actingAs($other, 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => 'Unauthorized',
                'severity' => BlockerSeverity::Low->value,
            ])
            ->assertForbidden();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_blocker(): void
    {
        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $this->actingAs($this->admin(), 'system')
            ->delete("/blockers/{$blocker->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('blockers', ['id' => $blocker->id]);
    }

    public function test_lead_can_delete_blocker(): void
    {
        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $this->actingAs($this->lead(), 'system')
            ->delete("/blockers/{$blocker->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('blockers', ['id' => $blocker->id]);
    }

    public function test_member_cannot_delete_blocker(): void
    {
        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $this->actingAs($this->member(), 'system')
            ->delete("/blockers/{$blocker->id}")
            ->assertForbidden();
    }

    // ─── Bulk ─────────────────────────────────────────────────────────────────

    public function test_admin_can_bulk_delete_blockers(): void
    {
        $project = Project::factory()->create();
        $b1 = $this->createBlocker($project);
        $b2 = $this->createBlocker($project);

        $this->actingAs($this->admin(), 'system')
            ->post('/blockers/bulk', [
                'ids' => [$b1->id, $b2->id],
                'action' => 'delete',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('blockers', ['id' => $b1->id]);
        $this->assertDatabaseMissing('blockers', ['id' => $b2->id]);
    }

    public function test_admin_can_bulk_update_status(): void
    {
        $project = Project::factory()->create();
        $b1 = $this->createBlocker($project);
        $b2 = $this->createBlocker($project);

        $this->actingAs($this->admin(), 'system')
            ->post('/blockers/bulk', [
                'ids' => [$b1->id, $b2->id],
                'action' => 'status',
                'status' => BlockerStatus::Resolved->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', ['id' => $b1->id, 'status' => BlockerStatus::Resolved->value]);
        $this->assertDatabaseHas('blockers', ['id' => $b2->id, 'status' => BlockerStatus::Resolved->value]);
    }

    // ─── Import ───────────────────────────────────────────────────────────────

    public function test_admin_can_import_blockers(): void
    {
        $project = Project::factory()->create();

        $rows = [
            ['title' => 'Row 1', 'severity' => BlockerSeverity::Low->value],
            ['title' => 'Row 2', 'severity' => BlockerSeverity::High->value],
        ];

        $this->actingAs($this->admin(), 'system')
            ->post('/blockers/import', [
                'project_id' => $project->id,
                'rows' => $rows,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', ['title' => 'Row 1', 'project_id' => $project->id]);
        $this->assertDatabaseHas('blockers', ['title' => 'Row 2', 'project_id' => $project->id]);
    }

    public function test_viewer_cannot_import_blockers(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->viewer(), 'system')
            ->post('/blockers/import', [
                'project_id' => $project->id,
                'rows' => [['title' => 'Row', 'severity' => BlockerSeverity::Low->value]],
            ])
            ->assertForbidden();
    }
}
