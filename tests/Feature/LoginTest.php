<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SystemAccount;
use App\Support\Auth\TechLoginAccess;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->has('googleAuthUrl')
                ->has('googleEnabled')
                ->has('ssoEnabled')
                ->has('ssoAuthUrl')
            );
    }

    public function test_google_oauth_redirect_prompts_account_selection(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get('/auth/google');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $decoded = urldecode($location);
        $this->assertStringContainsString('accounts.google.com/AccountChooser', $location);
        $this->assertStringContainsString('continue=', $location);
        $this->assertStringContainsString('/o/oauth2/', $decoded);
    }

    public function test_tech_login_page_renders(): void
    {
        $this->get('/tech/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->has('googleAuthUrl')
                ->has('googleEnabled')
                ->has('ssoEnabled')
                ->has('ssoAuthUrl')
            );
    }

    public function test_password_login_disabled_when_config_off(): void
    {
        config(['va.password_login_enabled' => false]);

        $this->post('/tech/login', [
            'username' => 'member',
            'password' => 'password',
        ])->assertNotFound();

        $this->post('/login', [
            'username' => 'member',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_member_can_login_via_portal_with_valid_credentials(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create([
            'username' => 'testuser',
            'password' => 'secret123',
        ]);

        $this->post('/login', [
            'username' => 'testuser',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($account, 'system');
    }

    public function test_tech_whitelisted_member_can_login_via_tech_portal(): void
    {
        $allowed = TechLoginAccess::allowedEmails()[0];

        $employee = Employee::factory()->create(['email' => $allowed]);
        $account = SystemAccount::factory()->role(SystemRole::Member)->forEmployee($employee)->create([
            'username' => 'techuser',
            'password' => 'secret123',
        ]);

        $this->post('/tech/login', [
            'username' => 'techuser',
            'password' => 'secret123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($account, 'system');
    }

    public function test_tech_login_rejects_non_whitelisted_employee_email(): void
    {
        $account = SystemAccount::factory()->role(SystemRole::Member)->create([
            'username' => 'outsider',
            'password' => 'secret123',
        ]);

        $this->post('/tech/login', [
            'username' => 'outsider',
            'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('system');
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

    public function test_authenticated_user_cannot_view_login_pages(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->get('/login')
            ->assertRedirect('/dashboard');

        $this->actingAs($account, 'system')
            ->get('/tech/login')
            ->assertRedirect('/dashboard');
    }

    public function test_user_can_logout(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest('system');
    }

    public function test_guest_app_route_redirects_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_root_redirects_to_dashboard(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_hidden_congnghe_paths_redirect_to_dashboard(): void
    {
        $account = SystemAccount::factory()->create();

        $this->actingAs($account, 'system')
            ->get('/congnghe')
            ->assertRedirect('/dashboard');

        $this->actingAs($account, 'system')
            ->get('/phongcongnghe')
            ->assertRedirect('/dashboard');
    }

    public function test_google_allowed_domains_accept_campus_subdomains(): void
    {
        config(['va.google_allowed_domains' => ['vaschools.edu.vn']]);

        $controller = app(\App\Http\Controllers\Auth\GoogleAuthController::class);
        $method = new \ReflectionMethod($controller, 'emailDomainAllowed');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'khoana@hcm.vaschools.edu.vn'));
        $this->assertTrue($method->invoke($controller, 'admin@vaschools.edu.vn'));
        $this->assertFalse($method->invoke($controller, 'outsider@gmail.com'));
    }
}
