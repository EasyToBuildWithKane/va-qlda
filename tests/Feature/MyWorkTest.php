<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyWorkTest extends TestCase
{
    use RefreshDatabase;

    private function accountFor(Employee $employee, SystemRole $role): SystemAccount
    {
        return SystemAccount::factory()->forEmployee($employee)->role($role)->create();
    }

    private function ledTeam(Employee $leader): OrgTeam
    {
        return OrgTeam::create([
            'name' => 'Đội '.$leader->id,
            'level' => 1,
            'leader_id' => $leader->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function addTeamMember(OrgTeam $team, Employee $employee): void
    {
        OrgTeamMember::create([
            'org_team_id' => $team->id,
            'employee_id' => $employee->id,
            'sort_order' => 1,
        ]);
    }

    private function taskFor(Project $project, Employee $assignee, array $overrides = []): Task
    {
        return $project->tasks()->create(array_merge([
            'title' => 'Việc được giao',
            'status' => TaskStatus::Todo->value,
            'priority' => 'medium',
            'assignee_id' => $assignee->id,
        ], $overrides));
    }

    // ─── Self view ──────────────────────────────────────────────────────────────

    public function test_member_sees_their_own_assigned_work(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);
        $project = Project::factory()->create();
        $this->taskFor($project, $employee, ['title' => 'Việc hôm nay', 'due_date' => now()->toDateString()]);

        $this->actingAs($account, 'system')
            ->get('/my-work')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyWork/Index')
                ->where('mode', 'self')
                ->where('summary.dueToday', 1)
                ->has('buckets.today', 1)
                ->where('buckets.today.0.title', 'Việc hôm nay'));
    }

    public function test_my_work_includes_pivot_assigned_tasks_and_excludes_done(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);
        $project = Project::factory()->create();

        // Gán qua pivot (không phải assignee chính).
        $pivotTask = $project->tasks()->create([
            'title' => 'Việc pivot', 'status' => TaskStatus::Todo->value, 'priority' => 'high',
        ]);
        $pivotTask->assignees()->attach($employee->id);

        // Việc đã xong không hiện mặc định.
        $this->taskFor($project, $employee, ['title' => 'Đã xong', 'status' => TaskStatus::Done->value]);

        $this->actingAs($account, 'system')
            ->get('/my-work')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.open', 1)
                ->where('buckets.no_due.0.title', 'Việc pivot'));
    }

    // ─── Team visibility (authorization) ─────────────────────────────────────────

    public function test_member_cannot_view_another_persons_work(): void
    {
        $me = Employee::factory()->create();
        $other = Employee::factory()->create();
        $account = $this->accountFor($me, SystemRole::Member);

        $this->actingAs($account, 'system')
            ->get('/my-work?member='.$other->id)
            ->assertForbidden();
    }

    public function test_lead_can_view_team_member_work(): void
    {
        $leadEmp = Employee::factory()->create();
        $memberEmp = Employee::factory()->create();
        $lead = $this->accountFor($leadEmp, SystemRole::Lead);
        $this->addTeamMember($this->ledTeam($leadEmp), $memberEmp);

        $this->actingAs($lead, 'system')
            ->get('/my-work?member='.$memberEmp->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MyWork/Index')
                ->where('mode', 'member')
                ->where('viewing.id', $memberEmp->id));
    }

    public function test_lead_cannot_view_non_team_member_work(): void
    {
        $leadEmp = Employee::factory()->create();
        $stranger = Employee::factory()->create();
        $lead = $this->accountFor($leadEmp, SystemRole::Lead);
        $this->ledTeam($leadEmp); // nhóm rỗng

        $this->actingAs($lead, 'system')
            ->get('/my-work?member='.$stranger->id)
            ->assertForbidden();
    }

    public function test_team_roster_lists_only_my_team_members(): void
    {
        $leadEmp = Employee::factory()->create();
        $memberEmp = Employee::factory()->create(['full_name' => 'Thành Viên A']);
        $lead = $this->accountFor($leadEmp, SystemRole::Lead);
        $this->addTeamMember($this->ledTeam($leadEmp), $memberEmp);

        $this->actingAs($lead, 'system')
            ->get('/my-work?scope=team')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('mode', 'team')
                ->has('team.members', 1)
                ->where('team.members.0.id', $memberEmp->id));
    }

    public function test_admin_can_view_any_member_work(): void
    {
        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $anyone = Employee::factory()->create();

        $this->actingAs($admin, 'system')
            ->get('/my-work?member='.$anyone->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('viewing.id', $anyone->id));
    }

    // ─── Act on team task (additive TaskPolicy@changeStatus) ─────────────────────

    public function test_lead_with_act_team_can_change_team_member_status_without_project_membership(): void
    {
        // Bỏ project.contribute khỏi lead để cô lập nhánh act_team.
        config(['va_permissions.role_grants.lead' => ['my_work.view_team', 'my_work.act_team']]);

        $leadEmp = Employee::factory()->create();
        $memberEmp = Employee::factory()->create();
        $lead = $this->accountFor($leadEmp, SystemRole::Lead);
        $this->addTeamMember($this->ledTeam($leadEmp), $memberEmp);

        $project = Project::factory()->create(); // lead KHÔNG phải thành viên/quản lý
        $task = $this->taskFor($project, $memberEmp);

        $this->actingAs($lead, 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", ['status' => TaskStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::InProgress->value]);
    }

    public function test_lead_without_contribute_cannot_change_non_team_member_status(): void
    {
        config(['va_permissions.role_grants.lead' => ['my_work.view_team', 'my_work.act_team']]);

        $leadEmp = Employee::factory()->create();
        $stranger = Employee::factory()->create();
        $lead = $this->accountFor($leadEmp, SystemRole::Lead);
        $this->ledTeam($leadEmp); // nhóm rỗng

        $project = Project::factory()->create();
        $task = $this->taskFor($project, $stranger);

        $this->actingAs($lead, 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", ['status' => TaskStatus::InProgress->value])
            ->assertForbidden();
    }

    public function test_project_member_can_still_change_status_via_contribute(): void
    {
        // Regression: nhánh contribute (board Kanban) không bị siết bởi TaskPolicy mới.
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);
        $project = Project::factory()->create();
        $project->members()->attach($employee->id, ['is_active' => true]);
        $task = $this->taskFor($project, $employee);

        $this->actingAs($account, 'system')
            ->patch("/projects/{$project->id}/tasks/{$task->id}", ['status' => TaskStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => TaskStatus::InProgress->value]);
    }

    public function test_spawned_daily_task_without_due_date_buckets_as_today(): void
    {
        $employee = Employee::factory()->create();
        $account = $this->accountFor($employee, SystemRole::Member);
        $project = Project::factory()->create();

        $task = $project->tasks()->create([
            'title' => 'Việc phát sinh báo cáo',
            'status' => TaskStatus::Todo->value,
            'priority' => 'medium',
            'assignee_id' => $employee->id,
            'source' => \App\Support\Enums\TaskSource::Daily->value,
        ]);

        $this->actingAs($account, 'system')
            ->get('/my-work')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dailyReportToday')
                ->where('buckets.today.0.id', $task->id)
                ->where('buckets.no_due', []));
    }

    public function test_guest_cannot_access_my_work(): void
    {
        $this->get('/my-work')->assertRedirect('/login');
    }
}
