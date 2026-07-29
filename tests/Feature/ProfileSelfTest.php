<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgTeam;
use App\Models\SystemAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hồ sơ của tôi" — HR identity + skills mirrored from VA-HRM (read-only).
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
                ->missing('editable')
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

    public function test_profile_update_route_is_removed(): void
    {
        $employee = Employee::factory()->create();
        $account = SystemAccount::factory()->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->put('/profile', [
                'skills' => [
                    ['name' => 'Vue', 'level' => 3, 'category' => 'Lập trình Web'],
                ],
            ])
            ->assertMethodNotAllowed();
    }
}
