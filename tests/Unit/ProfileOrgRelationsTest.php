<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\OrgTeamMember;
use App\Support\Profile\ProfileOrgRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileOrgRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_manager_is_parent_team_leader(): void
    {
        $parentLeader = Employee::factory()->create(['full_name' => 'Parent Lead']);
        $teamLead = Employee::factory()->create(['full_name' => 'Team Lead']);
        $member = Employee::factory()->create(['full_name' => 'Member']);

        $parent = OrgTeam::create([
            'name' => 'Ban quản lý',
            'level' => 1,
            'leader_id' => $parentLeader->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $child = OrgTeam::create([
            'name' => 'Nhóm A',
            'parent_id' => $parent->id,
            'level' => 2,
            'leader_id' => $teamLead->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        OrgTeamMember::create([
            'org_team_id' => $child->id,
            'employee_id' => $member->id,
            'sort_order' => 0,
        ]);

        $member->load([
            'orgMemberships.team.parent.leader',
            'orgMemberships.team.leader',
        ]);

        $manager = ProfileOrgRelations::directManager($member);

        $this->assertNotNull($manager);
        $this->assertSame($parentLeader->id, $manager->id);
    }

    public function test_department_code_from_qlda_not_cms_id(): void
    {
        Department::create([
            'code' => 'PB-PT',
            'name' => 'Phòng Công nghệ',
            'color' => 'sky',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $code = ProfileOrgRelations::departmentCode([
            'department_name' => 'Phòng Công nghệ',
            'department_id' => 999,
        ]);

        $this->assertSame('PB-PT', $code);
    }

    public function test_concurrent_position_lists_led_teams(): void
    {
        $employee = Employee::factory()->create();
        OrgTeam::create([
            'name' => 'Nhóm triển khai',
            'level' => 2,
            'leader_id' => $employee->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $employee->load('ledTeams');

        $label = ProfileOrgRelations::concurrentPositionLabel($employee);

        $this->assertStringContainsString('Trưởng nhóm', (string) $label);
        $this->assertStringContainsString('Nhóm triển khai', (string) $label);
    }
}
