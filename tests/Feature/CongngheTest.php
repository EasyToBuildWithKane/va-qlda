<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Auth\TechLoginAccess;
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
                ->has('org.overview')
                ->has('org.forest')
                ->where('portal.canEnterQlda', false)
            );
    }

    public function test_whitelisted_email_sees_qlda_entry_on_congnghe(): void
    {
        $allowed = TechLoginAccess::allowedEmails()[0];
        $employee = Employee::factory()->create(['email' => $allowed]);
        $account = SystemAccount::factory()->role(SystemRole::Member)->forEmployee($employee)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('portal.canEnterQlda', true)
                ->where('portal.qldaHome', '/dashboard')
            );
    }

    public function test_admin_can_enter_qlda_from_congnghe_portal_props(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Admin)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertInertia(fn ($page) => $page->where('portal.canEnterQlda', true));
    }

    public function test_viewer_can_view_congnghe(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Viewer)->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertOk();
    }
}
