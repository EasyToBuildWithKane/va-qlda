<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_team_dashboard(): void
    {
        $user = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($user, 'system')
            ->get(route('dashboard.team'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Team')
                ->has('members')
                ->has('weeklyTrend')
                ->has('projectTaskStats'));
    }

    public function test_guest_is_redirected_from_team_dashboard(): void
    {
        $this->get(route('dashboard.team'))->assertRedirect('/login');
    }
}
