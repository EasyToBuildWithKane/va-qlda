<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Models\Project;
use App\Models\SystemAccount;
use App\Models\Worklog;
use App\Support\Enums\SystemRole;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_directory_index_is_available_to_authenticated_users(): void
    {
        Employee::factory()->count(2)->create();

        $this->actingAs(SystemAccount::factory()->role(SystemRole::Member)->create(), 'system')
            ->get('/members')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Member/Index'));
    }

    public function test_member_profile_show_includes_identity_and_performance_for_admin(): void
    {
        $employee = Employee::factory()->create([
            'meta' => [
                'bio' => 'Bio',
                'level' => 'senior',
                'skill_details' => [
                    ['name' => 'Laravel', 'level' => 5, 'category' => 'backend'],
                ],
            ],
        ]);

        $leader = Employee::factory()->create();
        $team = OrgTeam::create(['name' => 'Team Alpha', 'level' => 1, 'leader_id' => $leader->id]);
        OrgTeamMember::create([
            'org_team_id' => $team->id,
            'employee_id' => $employee->id,
        ]);

        $project = Project::factory()->create();
        $project->members()->attach($employee->id, [
            'role' => 'dev',
            'allocation' => 50,
            'joined_at' => now()->subMonth(),
            'is_active' => true,
        ]);

        $task = $project->tasks()->create([
            'title' => 'Done task',
            'status' => TaskStatus::Done,
            'priority' => TaskPriority::Medium,
            'assignee_id' => $employee->id,
            'completed_at' => now(),
        ]);

        Worklog::create([
            'task_id' => $task->id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'hours' => 2,
        ]);

        $this->actingAs(SystemAccount::factory()->role(SystemRole::Admin)->create(), 'system')
            ->get(route('members.show', $employee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Member/Show')
                ->where('profile.id', $employee->id)
                ->where('canViewPerformance', true)
                ->has('stats')
                ->has('projectExperience')
                ->has('activity')
            );
    }

    public function test_member_profile_show_with_org_section_does_not_error(): void
    {
        $employee = Employee::factory()->create();
        $team = OrgTeam::create(['name' => 'Team Alpha', 'level' => 1]);
        $section = \App\Models\OrgTeamSection::create([
            'org_team_id' => $team->id,
            'title' => 'Nhánh GVS',
            'sort_order' => 0,
        ]);
        OrgTeamMember::create([
            'org_team_id' => $team->id,
            'employee_id' => $employee->id,
            'section_id' => $section->id,
        ]);

        $this->actingAs(SystemAccount::factory()->role(SystemRole::Admin)->create(), 'system')
            ->get(route('members.show', $employee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profile.teams.0.section', 'Nhánh GVS'));
    }

    public function test_member_profile_hides_performance_from_other_members(): void
    {
        $employee = Employee::factory()->create();
        $viewer = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($viewer, 'system')
            ->get(route('members.show', $employee))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canViewPerformance', false)
                ->where('stats', null)
            );
    }
}
