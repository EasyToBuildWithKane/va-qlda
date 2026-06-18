<?php

namespace App\Providers;

use App\Support\Auth\PermissionCatalog;
use App\Support\Settings\SettingsRepository;
use App\Support\Settings\SettingsSchema;
use Illuminate\Support\ServiceProvider;

/**
 * Overlays admin-saved settings onto config() at boot, so existing code that
 * reads config('telegram.*'), config('va.*') or config('va_permissions.*')
 * transparently picks up runtime overrides — no call sites change.
 *
 * Registered right after AppServiceProvider in config/app.php so it boots
 * before RouteServiceProvider (routes/web.php reads va.password_login_enabled
 * at registration time).
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $overrides = $this->app->make(SettingsRepository::class)->overrides();
        if ($overrides === []) {
            return; // empty/un-migrated table → leave config untouched
        }

        $map = SettingsSchema::configMap();

        foreach ($overrides as $key => $value) {
            $path = $map[$key] ?? null;
            if ($path === null) {
                continue;
            }

            if ($key === SettingsSchema::MATRIX_KEY) {
                $value = is_array($value) ? $value : [];
                // The locked role (super_admin) is never editable from the UI —
                // keep full access to prevent an accidental lock-out.
                $value[SettingsSchema::LOCKED_ROLE] = ['*'];
                // Reserved keys (system config, permission matrix, role assign)
                // belong to super_admin only — strip from every other role even
                // if a stale override or hand-edit slipped them in.
                foreach ($value as $role => $keys) {
                    if ($role !== SettingsSchema::LOCKED_ROLE && is_array($keys)) {
                        $value[$role] = PermissionCatalog::withoutReserved($keys);
                    }
                }
            }

            config([$path => $value]);
        }
    }
}
