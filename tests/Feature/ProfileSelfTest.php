<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SystemAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hồ sơ của tôi" — self-service profile, derived stats, and the partial-safe
 * update contract that lets the identity editor and the skill-matrix editor
 * save independently without clobbering each other.
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

    public function test_skills_only_update_preserves_identity(): void
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

    public function test_identity_only_update_preserves_skills(): void
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
            ->put(route('profile.update'), [
                'phone' => '0999999999',
                'role_title' => 'Lead',
                'bio' => 'Xin chào',
                'location' => 'Hà Nội',
                'github' => '',
                'linkedin' => '',
                'portfolio' => '',
                'website' => '',
            ])
            ->assertRedirect();

        $employee->refresh();

        $this->assertSame('0999999999', $employee->phone);
        $this->assertSame('Lead', $employee->role_title);
        $this->assertSame(['Laravel'], $employee->skills);
        $this->assertSame(5, $employee->meta['skill_details'][0]['level']);
    }
}
