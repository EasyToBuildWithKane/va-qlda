<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Project;
use App\Models\SecurityAuditLog;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskStatus;
use App\Support\ProjectActivityFeedBuilder;
use App\Support\ProjectActivityLogger;
use App\Support\TaskActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_activity_logger_and_feed_include_task_events(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Lead)->create();
        $project = Project::factory()->create();
        $task = Task::create([
            'project_id' => $project->id,
            'title' => 'Việc A',
            'status' => TaskStatus::Todo->value,
            'order_column' => 1,
        ]);

        ProjectActivityLogger::created($project, $account);
        TaskActivityLogger::created($task, $account);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'event' => 'created',
        ]);

        $feed = app(ProjectActivityFeedBuilder::class)->forProject($project);
        $this->assertNotEmpty($feed);
        $this->assertTrue(collect($feed)->contains(fn ($row) => str_contains($row['message'], 'Việc A')));
    }

    public function test_blocker_delete_writes_project_activity(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $project = Project::factory()->create();
        $blocker = Blocker::create([
            'project_id' => $project->id,
            'title' => 'Vướng mắc xoá',
            'severity' => 'medium',
            'status' => 'open',
            'raised_at' => now(),
        ]);

        $this->actingAs($account, 'system')
            ->delete("/blockers/{$blocker->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('blockers', ['id' => $blocker->id]);
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'event' => 'blocker_deleted',
        ]);
    }

    public function test_security_audit_log_can_be_written(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();

        SecurityAuditLog::create([
            'actor_account_id' => $account->id,
            'action' => 'ai_account.password_viewed',
            'subject_type' => 'ai_account',
            'subject_id' => 1,
            'meta' => ['label' => 'Test'],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('security_audit_logs', [
            'actor_account_id' => $account->id,
            'action' => 'ai_account.password_viewed',
        ]);
    }

    public function test_account_role_change_writes_security_audit_log(): void
    {
        $super = SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
        $target = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($super, 'system')
            ->put("/settings/accounts/{$target->id}/role", ['role' => SystemRole::Lead->value])
            ->assertRedirect();

        $this->assertDatabaseHas('security_audit_logs', [
            'actor_account_id' => $super->id,
            'action' => 'account.role_changed',
            'subject_id' => $target->id,
        ]);
    }

    public function test_audit_page_is_visible_to_admin_and_blocked_for_member(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($admin, 'system')->get('/audit')->assertOk();
        $this->actingAs($member, 'system')->get('/audit')->assertForbidden();
    }

    public function test_audit_action_catalog_describes_known_and_unknown_actions(): void
    {
        $known = \App\Support\Audit\AuditActionCatalog::describe('auth.login');
        $this->assertSame('auth', $known['module']);
        $this->assertSame('Đăng nhập', $known['label']);

        // Unknown action falls back safely without throwing.
        $unknown = \App\Support\Audit\AuditActionCatalog::describe('made_up.action');
        $this->assertSame('made_up.action', $unknown['label']);
        $this->assertSame('system', $unknown['module']);

        $unknownAi = \App\Support\Audit\AuditActionCatalog::describe('ai_account.legacy_event');
        $this->assertSame('ai_account', $unknownAi['module']);
        $this->assertSame('sparkles', $unknownAi['icon']);
    }

    public function test_audit_index_returns_resolved_logs_with_labels(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();

        SecurityAuditLog::create([
            'actor_account_id' => $admin->id,
            'action' => 'contract.created',
            'subject_type' => 'contract',
            'subject_id' => 42,
            'meta' => ['code' => 'HD-001', 'name' => 'Hợp đồng thuê'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'system')
            ->get('/audit?module=contract&page=1&per_page=25');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Audit/Index')
            ->has('logs', 1)
            ->where('logs.0.action_label', 'Tạo hợp đồng')
            ->where('logs.0.module_label', 'Quản lý hợp đồng')
            ->where('logs.0.subject_summary', 'Hợp đồng#42 · HD-001')
            ->where('logs.0.detail_preview', 'Mã: HD-001 · Tên: Hợp đồng thuê')
        );
    }

    public function test_audit_action_catalog_module_filter_includes_prefix_actions(): void
    {
        $prefixes = \App\Support\Audit\AuditActionCatalog::actionPrefixesForModule('contract');
        $this->assertContains('contract', $prefixes);
        $this->assertContains('vendor', $prefixes);
    }
}
