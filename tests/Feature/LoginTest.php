<?php

namespace Tests\Feature;

use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_member_can_login_with_valid_credentials(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create([
            'username' => 'testuser',
            'password' => 'secret123',
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'secret123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($account, 'system');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        SystemAccount::factory()->create([
            'username' => 'testuser',
            'password' => 'correct',
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrong',
        ])->assertSessionHasErrors();

        $this->assertGuest('system');
    }

    public function test_login_fails_for_inactive_account(): void
    {
        SystemAccount::factory()->create([
            'username' => 'inactive',
            'password' => 'secret123',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => 'inactive',
            'password' => 'secret123',
        ])->assertSessionHasErrors();

        $this->assertGuest('system');
    }

    public function test_authenticated_user_cannot_view_login_page(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->get('/login')
            ->assertRedirect();
    }

    public function test_user_can_logout(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest('system');
    }
}
