<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\BlockerAttachment;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

        $response = $this->actingAs($this->member(), 'system')
            ->post('/blockers', $this->blockerPayload($project->id))
            ->assertRedirect();

        $response->assertSessionHas('created_blocker_id');

        $this->assertDatabaseHas('blockers', [
            'project_id' => $project->id,
            'title' => 'Vướng mắc thử nghiệm',
        ]);
    }

    public function test_member_can_create_blocker_without_project(): void
    {
        $this->actingAs($this->member(), 'system')
            ->post('/blockers', [
                'title' => 'Thắc mắc chung thử',
                'severity' => BlockerSeverity::Medium->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blockers', [
            'project_id' => null,
            'title' => 'Thắc mắc chung thử',
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

    public function test_admin_can_bulk_create_blockers(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin(), 'system')
            ->post('/blockers/bulk-create', [
                'defaults' => [
                    'project_id' => $project->id,
                    'severity' => BlockerSeverity::High->value,
                    'status' => BlockerStatus::Open->value,
                ],
                'rows' => [
                    ['title' => 'Vướng mắc A'],
                    ['title' => 'Vướng mắc B'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('blockers', [
            'project_id' => $project->id,
            'title' => 'Vướng mắc A',
            'severity' => BlockerSeverity::High->value,
        ]);
        $this->assertDatabaseHas('blockers', [
            'project_id' => $project->id,
            'title' => 'Vướng mắc B',
        ]);
    }

    public function test_resolving_blocker_notifies_telegram_blocker_chat_when_configured(): void
    {
        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100999',
            'telegram.blocker_resolved' => true,
            'telegram.blocker_chat_id' => '-100888',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $project = Project::factory()->create([
            'code' => 'PRJ-025',
            'name' => 'Chi tiết chi phí chuyến',
        ]);
        $lead = $this->lead();
        $blocker = $this->createBlocker($project, $lead);
        $blocker->update([
            'title' => 'Chờ API HRM',
            'resolution' => 'Đã phối hợp team HRM xong endpoint.',
        ]);

        $this->actingAs($lead, 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => 'Chờ API HRM',
                'severity' => BlockerSeverity::High->value,
                'status' => BlockerStatus::Resolved->value,
                'resolution' => 'Đã phối hợp team HRM xong endpoint.',
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) use ($project) {
            return str_contains($request->url(), 'api.telegram.org/bottest-bot-token/sendMessage')
                && ($request['chat_id'] ?? null) === '-100888'
                && str_contains($request['text'], 'Vướng mắc đã xử lý')
                && str_contains($request['text'], 'Chuyển trạng thái')
                && str_contains($request['text'], 'Chờ API HRM')
                && str_contains($request['text'], 'Đã phối hợp team HRM')
                && str_contains($request['text'], '[Dự án] PRJ-025 — Chi tiết chi phí chuyến')
                && str_contains($request['text'], '[Cập nhật trạng thái lúc]')
                && str_contains($request['text'], "/projects/{$project->id}?tab=blockers");
        });
    }

    public function test_in_progress_status_notifies_telegram_blocker_chat(): void
    {
        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.blocker_chat_id' => '-100888',
            'telegram.blocker_resolved' => true,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $this->actingAs($this->admin(), 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => $blocker->title,
                'severity' => BlockerSeverity::High->value,
                'status' => BlockerStatus::InProgress->value,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Vướng mắc đang xử lý')
                && str_contains($request['text'], 'Đang mở →')
                && str_contains($request['text'], '[Cập nhật trạng thái lúc]');
        });
    }

    public function test_resolved_blocker_cannot_change_status(): void
    {
        Http::fake();
        config(['telegram.enabled' => false]);

        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);
        $blocker->update(['status' => BlockerStatus::Resolved->value, 'resolved_at' => now()]);

        $this->actingAs($this->admin(), 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => $blocker->title,
                'severity' => BlockerSeverity::High->value,
                'status' => BlockerStatus::Open->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(BlockerStatus::Resolved, $blocker->fresh()->status);
    }

    public function test_raiser_recheck_fail_reopens_blocker_and_notifies_telegram(): void
    {
        config([
            'telegram.enabled' => true,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.blocker_resolved' => true,
            'telegram.blocker_chat_id' => '-100888',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $project = Project::factory()->create();
        $raiser = $this->member();
        $blocker = $this->createBlocker($project, $raiser);
        $blocker->update([
            'status' => BlockerStatus::Resolved->value,
            'resolved_at' => now(),
            'recheck_result' => 'pending',
            'resolution' => 'Đã sửa xong theo yêu cầu.',
        ]);

        $this->actingAs($raiser, 'system')
            ->post("/blockers/{$blocker->id}/recheck", [
                'result' => 'failed',
                'note' => 'Chưa đúng quy trình phê duyệt.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $blocker->refresh();
        $this->assertSame(BlockerStatus::InProgress, $blocker->status);
        $this->assertSame('failed', $blocker->recheck_result->value);
        $this->assertSame('Chưa đúng quy trình phê duyệt.', $blocker->recheck_note);
        $this->assertNull($blocker->resolved_at);

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Kiểm tra lại không đạt')
                && str_contains($request['text'], 'Chưa đúng quy trình phê duyệt.');
        });
    }

    public function test_raiser_recheck_pass_closes_blocker(): void
    {
        config(['telegram.enabled' => false]);
        Http::fake();

        $project = Project::factory()->create();
        $raiser = $this->member();
        $blocker = $this->createBlocker($project, $raiser);
        $blocker->update([
            'status' => BlockerStatus::Resolved->value,
            'resolved_at' => now(),
            'recheck_result' => 'pending',
        ]);

        $this->actingAs($raiser, 'system')
            ->post("/blockers/{$blocker->id}/recheck", [
                'result' => 'passed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $blocker->fresh();
        $this->assertSame(BlockerStatus::Closed, $fresh->status);
        $this->assertSame('passed', $fresh->recheck_result->value);
    }

    public function test_admin_cannot_recheck_blocker(): void
    {
        config(['telegram.enabled' => false]);
        Http::fake();

        $project = Project::factory()->create();
        $raiser = $this->member();
        $blocker = $this->createBlocker($project, $raiser);
        $blocker->update([
            'status' => BlockerStatus::Resolved->value,
            'resolved_at' => now(),
            'recheck_result' => 'pending',
        ]);

        $this->actingAs($this->admin(), 'system')
            ->post("/blockers/{$blocker->id}/recheck", [
                'result' => 'passed',
            ])
            ->assertForbidden();
    }

    public function test_resolving_blocker_sets_recheck_pending(): void
    {
        config(['telegram.enabled' => false]);
        Http::fake();

        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $this->actingAs($this->admin(), 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => $blocker->title,
                'severity' => BlockerSeverity::High->value,
                'status' => BlockerStatus::Resolved->value,
            ])
            ->assertRedirect();

        $fresh = $blocker->fresh();
        $this->assertSame(BlockerStatus::Resolved, $fresh->status);
        $this->assertSame('pending', $fresh->recheck_result->value);
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

    public function test_member_can_download_blocker_attachment(): void
    {
        Storage::fake('public');

        $project = Project::factory()->create();
        $member = $this->member();
        $blocker = $this->createBlocker($project, $member);

        $path = UploadedFile::fake()->image('proof.png')->store('blockers/'.$blocker->id, 'public');
        $attachment = BlockerAttachment::create([
            'blocker_id' => $blocker->id,
            'uploaded_by_id' => $member->employee_id,
            'original_name' => 'proof.png',
            'path' => $path,
            'mime_type' => 'image/png',
            'size' => 1024,
            'is_image' => true,
        ]);

        $this->actingAs($member, 'system')
            ->get("/blockers/{$blocker->id}/attachments/{$attachment->id}/file")
            ->assertOk();
    }

    public function test_admin_can_save_evidence_links_on_blocker(): void
    {
        $project = Project::factory()->create();
        $blocker = $this->createBlocker($project);

        $links = [
            ['label' => 'Jira', 'url' => 'https://example.com/jira/1'],
            ['url' => 'https://example.com/log'],
        ];

        $this->actingAs($this->admin(), 'system')
            ->put("/blockers/{$blocker->id}", [
                'title' => $blocker->title,
                'severity' => BlockerSeverity::High->value,
                'status' => BlockerStatus::Open->value,
                'evidence_links' => $links,
            ])
            ->assertRedirect();

        $blocker->refresh();
        $this->assertSame($links, $blocker->evidence_links);
    }
}
