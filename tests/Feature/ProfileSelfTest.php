<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\SystemAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hồ sơ của tôi" — HR identity is mirrored from VA-HRM (read-only).
 * Self-service PUT only updates the Workspace skill matrix.
 */
class ProfileSelfTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_exposes_derived_stats_and_skill_radar(): void
    {
        $employee = Employee::factory()->create([
            'skills' => ['Laravel'],
            'meta' => ['skill_details' => [
                ['name' => 'Laravel', 'level' => 4, 'category' => 'backend'],
            ]],
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Show')
                ->where('editable', true)
                ->has('profile.stats')
                ->where('profile.stats.skill_score', 80)
                ->has('profile.stats.skill_radar', 1)
                ->has('profile.stats.profile_completion')
                ->has('profile.hr_info')
                ->where('profile.hr_info.code', $employee->code)
            );
    }

    public function test_show_team_leader_without_membership_row_lists_led_team(): void
    {
        $leader = Employee::factory()->create();
        OrgTeam::create(['name' => 'Team Led Only', 'level' => 1, 'leader_id' => $leader->id]);
        $account = SystemAccount::factory()->forEmployee($leader)->create();

        $this->actingAs($account, 'system')
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Show')
                ->has('profile.teams', 1)
                ->where('profile.teams.0.name', 'Team Led Only')
                ->where('profile.teams.0.is_leader', true)
            );
    }

    public function test_skills_update_preserves_hr_identity_fields(): void
    {
        $employee = Employee::factory()->create([
            'phone' => '0911111111',
            'role_title' => 'Senior Dev',
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->put(route('profile.update'), [
                'skills' => [
                    ['name' => 'Vue', 'level' => 3, 'category' => 'Lập trình Web', 'note' => 'Dự án nội bộ'],
                ],
            ])
            ->assertRedirect();

        $employee->refresh();

        $this->assertSame('0911111111', $employee->phone);
        $this->assertSame('Senior Dev', $employee->role_title);
        $this->assertSame(['Vue'], $employee->skills);
        $this->assertSame('Lập trình Web', $employee->meta['skill_details'][0]['category']);
        $this->assertSame('Dự án nội bộ', $employee->meta['skill_details'][0]['note']);
    }

    public function test_hr_identity_fields_are_rejected_on_update(): void
    {
        $employee = Employee::factory()->create([
            'phone' => '0900000000',
            'skills' => ['Laravel'],
            'meta' => ['skill_details' => [
                ['name' => 'Laravel', 'level' => 5, 'category' => 'backend'],
            ]],
        ]);
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->from(route('profile.show'))
            ->put(route('profile.update'), [
                'phone' => '0999999999',
                'role_title' => 'Lead',
                'bio' => 'Xin chào',
                'location' => 'Hà Nội',
                'skills' => [
                    ['name' => 'Laravel', 'level' => 5, 'category' => 'backend'],
                ],
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['phone', 'role_title', 'bio', 'location']);

        $employee->refresh();

        $this->assertSame('0900000000', $employee->phone);
        $this->assertSame(['Laravel'], $employee->skills);
        $this->assertSame(5, $employee->meta['skill_details'][0]['level']);
    }
}
