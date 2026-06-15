<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CongngheTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_congnghe(): void
    {
        $this->get('/congnghe')->assertRedirect(route('tech.login'));
    }

    public function test_authenticated_member_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Congnghe/Index')
                ->has('metrics')
                ->has('metrics.projects')
                ->has('metrics.members')
                ->has('phases')
                ->has('products')
                ->has('team')
                ->has('org.overview')
                ->has('org.forest')
            );
    }

    public function test_viewer_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk();
    }
}
