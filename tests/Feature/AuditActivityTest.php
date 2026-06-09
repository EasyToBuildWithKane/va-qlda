<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Project;
use App\Models\SecurityAuditLog;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\BugActivityLogger;
use App\Support\Enums\BugSeverity;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
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

    public function test_bug_create_writes_bug_activity(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Lead)->create();
        $project = Project::factory()->create();

        $bug = Bug::create([
            'project_id' => $project->id,
            'title' => 'Lỗi login',
            'severity' => BugSeverity::Major->value,
            'priority' => TaskPriority::Medium->value,
            'status' => 'open',
        ]);
        BugActivityLogger::created($bug, $account);

        $this->assertDatabaseHas('bug_activities', [
            'bug_id' => $bug->id,
            'event' => 'created',
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
}
