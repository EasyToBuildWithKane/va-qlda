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

    private function superAdmin(): SystemAccount
    {
        return SystemAccount::factory()->role(SystemRole::SuperAdmin)->create();
    }

    private function repo(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    // ─── Access ─────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_settings(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('groups', 7) // general, auth, telegram, email, clm, permissions, accounts
                ->has('emailTemplates', 5)
                ->where('can.manage', true)
            );
    }

    public function test_admin_and_below_cannot_view_settings(): void
    {
        // System configuration is now super-admin only — admin loses access too.
        foreach ([SystemRole::Admin, SystemRole::Lead, SystemRole::Member, SystemRole::Viewer] as $role) {
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
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/general', [
                'app_name' => 'Hệ thống Workspace',
                'app_short_name' => 'Workspace',
                'support_email' => 'it@vaschools.edu.vn',
                'app_version' => '2.0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'general.app_short_name']);
        $this->assertSame('Workspace', $this->repo()->get('general.app_short_name'));
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
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/telegram', ['bot_token' => ''])
            ->assertRedirect();

        $this->assertSame('SECRET-123', $this->repo()->get('telegram.bot_token'));
    }

    public function test_secret_is_masked_in_payload(): void
    {
        $this->repo()->setMany(['telegram.bot_token' => 'SECRET-123']);

        $this->actingAs($this->superAdmin(), 'system')
            ->get('/settings')
            ->assertInertia(function ($page) {
                $telegram = collect($page->toArray()['props']['settings']['telegram']);
                $token = $telegram->firstWhere('name', 'bot_token');

                $this->assertSame('', $token['value']);      // never leaked
                $this->assertTrue($token['has_value']);       // but UI knows it's set
            });
    }

    // ─── Permissions matrix ─────────────────────────────────────────────────

    public function test_super_admin_can_edit_permission_matrix_and_super_stays_full(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/permissions', [
                'grants' => [
                    'admin' => ['project.create', 'contract.manage'],
                    'lead' => ['notification.manage', 'project.manage'],
                    'member' => ['daily_report.create'],
                    'viewer' => [],
                    // super_admin intentionally omitted by the client
                ],
            ])
            ->assertRedirect();

        $grants = $this->repo()->get(SettingsSchema::MATRIX_KEY);

        $this->assertContains('notification.manage', $grants['lead']);
        $this->assertContains('contract.manage', $grants['admin']);
        $this->assertSame([], $grants['viewer']);
        $this->assertSame(['*'], $grants['super_admin']); // forced — never lockable
    }

    public function test_reserved_keys_are_stripped_for_editable_roles(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/permissions', [
                'grants' => [
                    // Attempt to grant admin the super-admin-only abilities.
                    'admin' => ['project.create', 'system.settings.manage', 'permissions.manage', 'roles.assign'],
                    'lead' => [],
                    'member' => [],
                    'viewer' => [],
                ],
            ])
            ->assertRedirect();

        $grants = $this->repo()->get(SettingsSchema::MATRIX_KEY);

        $this->assertContains('project.create', $grants['admin']);
        $this->assertNotContains('system.settings.manage', $grants['admin']);
        $this->assertNotContains('permissions.manage', $grants['admin']);
        $this->assertNotContains('roles.assign', $grants['admin']);
    }

    public function test_invalid_permission_key_is_rejected(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
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

    public function test_overlay_applies_ai_reminder_and_google_email_lists(): void
    {
        $this->repo()->setMany([
            'email.ai_reminder_enabled' => false,
            'email.ai_reminder_extra_emails' => ['extra@vaschools.edu.vn'],
            'auth.google_allowed_emails' => ['guest@gmail.com'],
        ]);
        config([
            'ai_accounts.reminder.send_email' => true,
            'ai_accounts.reminder.extra_recipients' => [],
            'va.google_allowed_emails' => [],
        ]);

        (new SettingsServiceProvider($this->app))->boot();

        $this->assertFalse(config('ai_accounts.reminder.send_email'));
        $this->assertSame(['extra@vaschools.edu.vn'], config('ai_accounts.reminder.extra_recipients'));
        $this->assertSame(['guest@gmail.com'], config('va.google_allowed_emails'));
    }

    public function test_admin_can_update_email_template(): void
    {
        $template = EmailTemplate::query()->where('key', EmailTemplate::KEY_TASK_ASSIGNED)->first();
        $this->assertNotNull($template);

        $this->actingAs($this->superAdmin(), 'system')
            ->put("/settings/email-templates/{$template->id}", [
                'subject' => 'Test subject {{task_name}}',
                'body_html' => '<p>Hello {{assignee_name}}</p>',
                'is_active' => true,
            ])
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Test subject {{task_name}}', $template->subject);
    }

    public function test_settings_email_lists_congnghe_proposal_templates(): void
    {
        $this->assertNotNull(
            EmailTemplate::query()->where('key', EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED)->first(),
        );
        $this->assertNotNull(
            EmailTemplate::query()->where('key', EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED)->first(),
        );

        $this->actingAs($this->superAdmin(), 'system')
            ->get(route('settings.show', ['group' => 'email']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeGroup', 'email')
                ->has('emailTemplates', 5)
                ->where('emailTemplates', function ($templates) {
                    $keys = collect($templates)->pluck('key')->all();

                    return in_array(EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED, $keys, true)
                        && in_array(EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED, $keys, true);
                })
            );
    }

    public function test_admin_can_send_test_email_template(): void
    {
        Mail::fake();

        $template = EmailTemplate::query()->where('key', EmailTemplate::KEY_TASK_ASSIGNED)->first();
        $this->assertNotNull($template);

        $this->actingAs($this->superAdmin(), 'system')
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

    public function test_overlay_forces_super_admin_wildcard_and_strips_reserved(): void
    {
        // Even if a stored matrix drops super_admin or leaks reserved keys to a
        // lower role, the overlay restores full access for super and strips them.
        $this->repo()->setMany([
            SettingsSchema::MATRIX_KEY => [
                'super_admin' => [],
                'admin' => ['project.create', 'system.settings.manage'],
            ],
        ]);

        (new SettingsServiceProvider($this->app))->boot();

        $grants = config('va_permissions.role_grants');
        $this->assertSame(['*'], $grants['super_admin']);
        $this->assertContains('project.create', $grants['admin']);
        $this->assertNotContains('system.settings.manage', $grants['admin']);
    }

    // ─── CLM group ──────────────────────────────────────────────────────────

    public function test_admin_can_update_clm_settings(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/clm', [
                'alert_enabled' => true,
                'renewal_alert_days' => '60,30,7',
                'alert_telegram' => false,
            ])
            ->assertRedirect();

        $this->assertSame('60,30,7', $this->repo()->get('clm.renewal_alert_days'));
        $this->assertTrue($this->repo()->get('clm.alert_enabled'));
    }

    public function test_clm_renewal_alert_days_rejects_non_numeric(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/clm', [
                'alert_enabled' => true,
                'renewal_alert_days' => 'abc,30',
                'alert_telegram' => false,
            ])
            ->assertSessionHasErrors('renewal_alert_days');
    }

    public function test_settings_payload_includes_clm_group(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->has('settings.clm')
                ->where('settings.clm.0.name', 'alert_enabled')
            );
    }

    // ─── Auth: google_allowed_emails (HTTP) ─────────────────────────────────

    public function test_admin_can_update_auth_google_allowed_emails(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/auth', [
                'password_login_enabled' => false,
                'google_allowed_domains' => ['vaschools.edu.vn'],
                'google_allowed_emails' => ['guest@gmail.com', 'other@gmail.com'],
                'tech_login_allowed_emails' => [],
            ])
            ->assertRedirect();

        $this->assertSame(
            ['guest@gmail.com', 'other@gmail.com'],
            $this->repo()->get('auth.google_allowed_emails'),
        );
    }

    // ─── Email: ai_reminder_* (HTTP) ────────────────────────────────────────

    public function test_admin_can_update_ai_reminder_settings(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/email', [
                'enabled' => false,
                'from_name' => 'VA Test',
                'notify_on_assign' => false,
                'notify_daily_at' => '09:00',
                'ai_reminder_enabled' => false,
                'ai_reminder_extra_emails' => ['extra@vaschools.edu.vn'],
                'ai_reminder_include_expired' => false,
                'ai_reminder_unpaid_renewal' => true,
            ])
            ->assertRedirect();

        $this->assertFalse($this->repo()->get('email.ai_reminder_enabled'));
        $this->assertSame(['extra@vaschools.edu.vn'], $this->repo()->get('email.ai_reminder_extra_emails'));
        $this->assertFalse($this->repo()->get('email.ai_reminder_include_expired'));
        $this->assertTrue($this->repo()->get('email.ai_reminder_unpaid_renewal'));
    }

    // ─── General: new fields ────────────────────────────────────────────────

    public function test_admin_can_update_general_new_fields(): void
    {
        $this->actingAs($this->superAdmin(), 'system')
            ->put('/settings/general', [
                'app_name' => 'VA Workspace Test',
                'app_short_name' => 'VA',
                'support_email' => 'it@vaschools.edu.vn',
                'app_version' => '2.0',
                'congnghe_proposal_email' => 'congnghe@vaschools.edu.vn',
                'dashboard_personnel_pattern' => 'Công nghệ',
            ])
            ->assertRedirect();

        $this->assertSame('congnghe@vaschools.edu.vn', $this->repo()->get('general.congnghe_proposal_email'));
        $this->assertSame('Công nghệ', $this->repo()->get('general.dashboard_personnel_pattern'));
    }

    // ─── settings:import-from-env command ───────────────────────────────────

    public function test_import_from_env_seeds_telegram_config(): void
    {
        config(['telegram.enabled' => true, 'telegram.chat_id' => '-9999']);

        $this->artisan('settings:import-from-env')
            ->assertSuccessful();

        // telegram.enabled is true (non-default false) → should be seeded
        $this->assertTrue((bool) $this->repo()->get('telegram.enabled'));
    }

    public function test_import_from_env_dry_run_does_not_write(): void
    {
        config(['telegram.chat_id' => '-12345']);

        $this->artisan('settings:import-from-env', ['--dry-run' => true])
            ->assertSuccessful();

        // dry-run must not persist anything to DB
        $this->assertDatabaseMissing('system_settings', ['key' => 'telegram.chat_id']);
    }

    public function test_import_from_env_skips_existing_without_force(): void
    {
        $this->repo()->setMany(['telegram.enabled' => false]);
        config(['telegram.enabled' => true]);

        $this->artisan('settings:import-from-env')
            ->assertSuccessful();

        // Should keep DB value (false), not overwrite with env (true)
        $this->assertFalse((bool) $this->repo()->get('telegram.enabled'));
    }

    public function test_import_from_env_force_overwrites_existing(): void
    {
        $this->repo()->setMany(['telegram.enabled' => false]);
        config(['telegram.enabled' => true]);

        $this->artisan('settings:import-from-env', ['--force' => true])
            ->assertSuccessful();

        $this->assertTrue((bool) $this->repo()->get('telegram.enabled'));
    }
}
