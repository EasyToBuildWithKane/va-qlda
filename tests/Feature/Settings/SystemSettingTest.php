<?php

namespace Tests\Feature\Settings;

use App\Mail\EmailTemplateTestMail;
use App\Models\EmailTemplate;
use App\Models\SystemAccount;
use App\Providers\SettingsServiceProvider;
use App\Support\Enums\SystemRole;
use App\Support\Settings\SettingsRepository;
use App\Support\Settings\SettingsSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The overrides cache is process-wide (array driver); clear it so a
        // rolled-back row from a prior test never leaks in.
        Cache::flush();
    }

    private function admin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::Admin)->create();
    }

    private function repo(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    // ─── Access ─────────────────────────────────────────────────────────────

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('groups', 5)
                ->has('emailTemplates', 3)
                ->where('can.manage', true)
            );
    }

    public function test_non_admins_cannot_view_settings(): void
    {
        foreach ([SystemRole::Lead, SystemRole::Member, SystemRole::Viewer] as $role) {
            $account = SystemAccount::factory()->role($role)->create();

            $this->actingAs($account, 'system')
                ->get('/settings')
                ->assertForbidden();
        }
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    // ─── Update ─────────────────────────────────────────────────────────────

    public function test_admin_can_update_general_settings(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->put('/settings/general', [
                'app_name' => 'Hệ thống QLDA',
                'app_short_name' => 'QLDA',
                'support_email' => 'it@vaschools.edu.vn',
                'app_version' => '2.0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'general.app_short_name']);
        $this->assertSame('QLDA', $this->repo()->get('general.app_short_name'));
    }

    public function test_member_cannot_update_settings(): void
    {
        $member = SystemAccount::factory()->role(SystemRole::Member)->create();

        $this->actingAs($member, 'system')
            ->put('/settings/general', ['app_name' => 'X', 'app_short_name' => 'X'])
            ->assertForbidden();
    }

    public function test_blank_secret_keeps_existing_token(): void
    {
        $this->repo()->setMany(['telegram.bot_token' => 'SECRET-123']);

        // Submitting a blank token must not wipe the stored one.
        $this->actingAs($this->admin(), 'system')
            ->put('/settings/telegram', ['bot_token' => ''])
            ->assertRedirect();

        $this->assertSame('SECRET-123', $this->repo()->get('telegram.bot_token'));
    }

    public function test_secret_is_masked_in_payload(): void
    {
        $this->repo()->setMany(['telegram.bot_token' => 'SECRET-123']);

        $this->actingAs($this->admin(), 'system')
            ->get('/settings')
            ->assertInertia(function ($page) {
                $telegram = collect($page->toArray()['props']['settings']['telegram']);
                $token = $telegram->firstWhere('name', 'bot_token');

                $this->assertSame('', $token['value']);      // never leaked
                $this->assertTrue($token['has_value']);       // but UI knows it's set
            });
    }

    // ─── Permissions matrix ─────────────────────────────────────────────────

    public function test_admin_can_edit_permission_matrix_and_admin_stays_full(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->put('/settings/permissions', [
                'grants' => [
                    'lead' => ['notifications.manage', 'projects.manage'],
                    'member' => ['daily_reports.submit'],
                    'viewer' => [],
                    // admin intentionally omitted by the client
                ],
            ])
            ->assertRedirect();

        $grants = $this->repo()->get(SettingsSchema::MATRIX_KEY);

        $this->assertContains('notifications.manage', $grants['lead']);
        $this->assertSame([], $grants['viewer']);
        $this->assertSame(['*'], $grants['admin']); // forced — never lockable
    }

    public function test_invalid_permission_key_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'system')
            ->put('/settings/permissions', [
                'grants' => ['lead' => ['totally.invalid'], 'member' => [], 'viewer' => []],
            ])
            ->assertSessionHasErrors('grants.lead.0');
    }

    // ─── Config overlay (runs at boot — invoke the provider directly) ────────

    public function test_overlay_applies_overrides_to_config(): void
    {
        $this->repo()->setMany(['telegram.enabled' => true]);
        config(['telegram.enabled' => false]); // simulate the boot-time default

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertTrue(config('telegram.enabled'));
    }

    public function test_admin_can_update_email_template(): void
    {
        $template = EmailTemplate::query()->where('key', EmailTemplate::KEY_TASK_ASSIGNED)->first();
        $this->assertNotNull($template);

        $this->actingAs($this->admin(), 'system')
            ->put("/settings/email-templates/{$template->id}", [
                'subject' => 'Test subject {{task_name}}',
                'body_html' => '<p>Hello {{assignee_name}}</p>',
                'is_active' => true,
            ])
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Test subject {{task_name}}', $template->subject);
    }

    public function test_admin_can_send_test_email_template(): void
    {
        Mail::fake();

        $template = EmailTemplate::query()->where('key', EmailTemplate::KEY_TASK_ASSIGNED)->first();
        $this->assertNotNull($template);

        $this->actingAs($this->admin(), 'system')
            ->post("/settings/email-templates/{$template->id}/test", [
                'email' => 'admin.test@vaschools.edu.vn',
                'subject' => 'Thử {{task_name}}',
                'body_html' => '<p>Xin chào {{assignee_name}}</p>',
            ])
            ->assertRedirect();

        Mail::assertSent(EmailTemplateTestMail::class, function (EmailTemplateTestMail $mail) {
            return str_contains($mail->mailSubject, 'Thử')
                && str_contains($mail->htmlBody, 'Xin chào Nguyễn');
        });
    }

    public function test_overlay_forces_admin_wildcard(): void
    {
        // Even if a stored matrix drops admin, the overlay restores full access.
        $this->repo()->setMany([
            SettingsSchema::MATRIX_KEY => ['lead' => ['projects.manage'], 'admin' => []],
        ]);

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame(['*'], config('va_permissions.role_grants')['admin']);
    }
}
