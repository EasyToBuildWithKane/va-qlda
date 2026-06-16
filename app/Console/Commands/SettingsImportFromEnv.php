<?php

namespace App\Console\Commands;

use App\Support\Settings\SettingsRepository;
use App\Support\Settings\SettingsSchema;
use Illuminate\Console\Command;

/**
 * Bootstraps system_settings from .env values so the admin panel reflects
 * what was previously configured in the environment file.
 *
 * Safe to run multiple times — existing DB overrides are preserved by default
 * (use --force to overwrite).
 *
 * Usage:
 *   php artisan settings:import-from-env            # skip keys already in DB
 *   php artisan settings:import-from-env --force    # overwrite all with .env values
 *   php artisan settings:import-from-env --dry-run  # show what would change
 */
class SettingsImportFromEnv extends Command
{
    protected $signature = 'settings:import-from-env
                            {--force   : Overwrite keys that already have a DB override}
                            {--dry-run : Show changes without writing to DB}';

    protected $description = 'Bootstrap system_settings DB from current .env / config values (idempotent)';

    /**
     * Map: schema key → env-backed config() path.
     * Keys without a meaningful .env fallback (DB-only defaults) are excluded.
     */
    private const ENV_BACKED_KEYS = [
        // general
        'general.app_name' => 'va.app_name',
        'general.app_version' => 'va.app_version',
        'general.support_email' => 'va.support_email',
        'general.congnghe_proposal_email' => 'va.congnghe_proposal_email',
        'general.dashboard_personnel_pattern' => 'va.dashboard_personnel_department_pattern',

        // auth
        'auth.password_login_enabled' => 'va.password_login_enabled',
        'auth.google_allowed_domains' => 'va.google_allowed_domains',
        'auth.google_allowed_emails' => 'va.google_allowed_emails',
        'auth.tech_login_allowed_emails' => 'va.tech_login_allowed_emails',

        // telegram
        'telegram.enabled' => 'telegram.enabled',
        'telegram.bot_token' => 'telegram.bot_token',
        'telegram.chat_id' => 'telegram.chat_id',
        'telegram.blocker_chat_id' => 'telegram.blocker_chat_id',
        'telegram.daily_report_review' => 'telegram.daily_report_review',
        'telegram.blocker_resolved' => 'telegram.blocker_resolved',

        // email
        'email.enabled' => 'task_email.enabled',
        'email.from_name' => 'task_email.from_name',
        'email.ai_reminder_enabled' => 'ai_accounts.reminder.send_email',
        'email.ai_reminder_extra_emails' => 'ai_accounts.reminder.extra_recipients',
        'email.ai_reminder_include_expired' => 'ai_accounts.reminder.include_expired',
        'email.ai_reminder_unpaid_renewal' => 'ai_accounts.reminder.include_unpaid_renewal',

        // clm
        'clm.alert_enabled' => 'clm.alert_enabled',
        'clm.renewal_alert_days' => 'clm.renewal_alert_days',
        'clm.alert_telegram' => 'clm.alert_telegram',
    ];

    public function handle(SettingsRepository $settings): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        /** @var array<string, mixed> $existing */
        $existing = $settings->overrides();
        $defaults = SettingsSchema::defaults();
        $rows = [];

        foreach (self::ENV_BACKED_KEYS as $key => $configPath) {
            if (! $force && array_key_exists($key, $existing)) {
                $this->line("  <fg=yellow>skip</>  {$key} (already in DB)");

                continue;
            }

            $envValue = config($configPath);

            // Skip if config matches schema default — nothing useful to write
            $default = $defaults[$key] ?? null;
            if ($envValue === $default && ! $force) {
                $this->line("  <fg=gray>same</>  {$key} (matches default, skipping)");

                continue;
            }

            // Skip empty secrets to avoid wiping a previously stored token
            if (SettingsSchema::type($key) === 'secret' && blank($envValue)) {
                $this->line("  <fg=gray>skip</>  {$key} (secret is blank — set via /settings)");

                continue;
            }

            $display = is_array($envValue) ? implode(', ', $envValue) : (string) $envValue;
            $this->line("  <fg=green>set </>  {$key} = {$display}");
            $rows[$key] = $envValue;
        }

        if ($rows === []) {
            $this->info('Không có gì để nhập. Mọi key đã đồng bộ hoặc khớp default.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('--dry-run: '.count($rows).' key sẽ được ghi (không thực thi).');

            return self::SUCCESS;
        }

        $settings->setMany($rows);
        $this->info('Đã nhập '.count($rows).' cấu hình vào system_settings.');
        $this->line('Chạy <info>php artisan config:clear</> nếu có cache cấu hình.');

        return self::SUCCESS;
    }
}
