<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewAsTest extends TestCase
{
    use RefreshDatabase;

    private function account(SystemRole $role): SystemAccount
    {
        return SystemAccount::factory()->role($role)->create();
    }

    public function test_super_admin_can_start_and_stop_a_preview(): void
    {
        $super = $this->account(SystemRole::SuperAdmin);

        $this->actingAs($super, 'system')
            ->post('/view-as', ['role' => SystemRole::Member->value])
            ->assertRedirect();

        $this->actingAs($super, 'system')
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.role', SystemRole::Member->value)
                ->where('auth.user.is_super_admin', false)
                ->where('viewAs.active', true)
                ->where('viewAs.role', SystemRole::Member->value)
                ->where('viewAs.realRole', SystemRole::SuperAdmin->value)
            );

        $this->actingAs($super, 'system')
            ->delete('/view-as')
            ->assertRedirect();

        $this->actingAs($super, 'system')
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.role', SystemRole::SuperAdmin->value)
                ->where('auth.user.is_super_admin', true)
                ->where('viewAs.active', false)
            );
    }

    public function test_non_super_admin_cannot_start_a_preview(): void
    {
        $admin = $this->account(SystemRole::Admin);

        $this->actingAs($admin, 'system')
            ->post('/view-as', ['role' => SystemRole::Member->value])
            ->assertForbidden();
    }

    public function test_preview_permissions_match_the_simulated_role_grants(): void
    {
        $super = $this->account(SystemRole::SuperAdmin);

        $this->actingAs($super, 'system')
            ->post('/view-as', ['role' => SystemRole::Viewer->value]);

        $expected = (array) config('va_permissions.role_grants')[SystemRole::Viewer->value];

        $this->actingAs($super, 'system')
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.permissions', $expected)
            );
    }

    public function test_real_super_admin_access_is_unaffected_while_previewing(): void
    {
        $super = $this->account(SystemRole::SuperAdmin);

        $this->actingAs($super, 'system')
            ->post('/view-as', ['role' => SystemRole::Member->value]);

        // /settings is super-admin-only at the controller/policy level — a real
        // member could never reach it, but the actual authenticated account is
        // still the real super_admin, so it must stay reachable while previewing.
        $this->actingAs($super, 'system')
            ->get('/settings')
            ->assertOk();
    }

    public function test_cannot_preview_super_admin_role(): void
    {
        $super = $this->account(SystemRole::SuperAdmin);

        $this->actingAs($super, 'system')
            ->post('/view-as', ['role' => SystemRole::SuperAdmin->value])
            ->assertSessionHasErrors('role');
    }
}
