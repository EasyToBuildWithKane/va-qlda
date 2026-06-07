<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\SystemAccount;
use App\Support\Enums\OrgTeamMemberBranch;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgTeamTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_guest_cannot_access_org_teams(): void
    {
        $this->get(route('org-teams.index'))->assertRedirect('/login');
    }

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get(route('org-teams.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('OrgTeam/Index'));
    }

    public function test_admin_can_create_three_level_tree(): void
    {
        $admin = $this->admin();
        $leader = Employee::factory()->create(['full_name' => 'Nguyễn Anh Khoa']);
        $devs = Employee::factory()->count(3)->create();

        $this->actingAs($admin, 'system')
            ->post(route('org-teams.store'), [
                'name' => 'Leader Phần Mềm',
                'leader_id' => $leader->id,
            ])
            ->assertRedirect();

        $l1 = OrgTeam::query()->where('name', 'Leader Phần Mềm')->first();
        $this->assertNotNull($l1);
        $this->assertSame(1, $l1->level);

        $this->actingAs($admin, 'system')
            ->post(route('org-teams.store'), [
                'name' => 'Đội ngũ Dev',
                'parent_id' => $l1->id,
                'members' => $devs->map(fn ($e, $i) => [
                    'employee_id' => $e->id,
                    'sort_order' => $i,
                ])->all(),
            ])
            ->assertRedirect();

        $l2 = OrgTeam::query()->where('name', 'Đội ngũ Dev')->first();
        $this->assertSame(2, $l2->level);
        $this->assertCount(3, $l2->members);

        $ba = Employee::factory()->create();
        $this->actingAs($admin, 'system')
            ->post(route('org-teams.store'), [
                'name' => 'Tổ BA',
                'parent_id' => $l2->id,
                'members' => [
                    [
                        'employee_id' => $ba->id,
                        'branch' => OrgTeamMemberBranch::Gvs->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $l3 = OrgTeam::query()->where('name', 'Tổ BA')->first();
        $this->assertSame(3, $l3->level);
        $this->assertSame(OrgTeamMemberBranch::Gvs, $l3->members->first()->branch);
    }

    public function test_cannot_add_fourth_level(): void
    {
        $admin = $this->admin();
        $l1 = OrgTeam::create(['name' => 'A', 'level' => 1]);
        $l2 = OrgTeam::create(['name' => 'B', 'level' => 2, 'parent_id' => $l1->id]);
        $l3 = OrgTeam::create(['name' => 'C', 'level' => 3, 'parent_id' => $l2->id]);

        $this->actingAs($admin, 'system')
            ->post(route('org-teams.store'), [
                'name' => 'D',
                'parent_id' => $l3->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }
}
