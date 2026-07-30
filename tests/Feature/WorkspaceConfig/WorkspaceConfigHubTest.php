<?php

namespace Tests\Feature\WorkspaceConfig;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceConfigHubTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    public function test_super_admin_can_view_hub(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/workspace-config')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkspaceConfig/Hub')
                ->has('items')
                ->has('summary')
                ->where('summary.total', 1)
            );
    }

    public function test_admin_cannot_view_hub(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/workspace-config')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/workspace-config')->assertRedirect('/login');
    }
}
