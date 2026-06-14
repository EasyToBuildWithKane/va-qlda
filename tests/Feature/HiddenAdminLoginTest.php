<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HiddenAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_login_page_renders(): void
    {
        $this->get('/lh36')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/HiddenAdminLogin'));
    }

    public function test_hidden_login_works_when_password_login_disabled(): void
    {
        config(['va.password_login_enabled' => false]);

        $account = SystemAccount::factory()->role(SystemRole::Admin)->create([
            'username' => 'usr_01',
            'password' => 'password01',
        ]);

        $this->post('/lh36', [
            'username' => 'usr_01',
            'password' => 'password01',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($account, 'system');
    }

    public function test_hidden_login_rejects_invalid_credentials(): void
    {
        SystemAccount::factory()->create([
            'username' => 'usr_01',
            'password' => 'password01',
        ]);

        $this->post('/lh36', [
            'username' => 'usr_01',
            'password' => 'wrong',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('system');
    }
}
