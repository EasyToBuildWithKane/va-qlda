<?php

namespace App\Support\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reads/writes admin overrides for {@see SettingsSchema} and resolves the
 * effective value (override → current config → schema default).
 *
 * Only the stored rows are exposed via {@see overrides()} so the runtime
 * overlay (SettingsServiceProvider) never clobbers config for un-saved keys.
 * Registered as a singleton in AppServiceProvider.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'system_settings.overrides';

    /**
     * Decoded values for the rows that actually exist in the DB.
     *
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! $this->tableReady()) {
                return [];
            }

            return SystemSetting::query()
                ->pluck('value', 'key')
                ->map(fn ($raw) => $raw === null ? null : json_decode($raw, true))
                ->all();
        });
    }

    /**
     * Effective value: DB override → current config() → schema default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        $configPath = SettingsSchema::configMap()[$key] ?? null;
        if ($configPath !== null) {
            $value = config($configPath);
            if ($value !== null) {
                return $value;
            }
        }

        return $default ?? (SettingsSchema::defaults()[$key] ?? null);
    }

    /**
     * Merged view (every schema key with its effective value) for the UI.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $merged = [];
        foreach (array_keys(SettingsSchema::defaults()) as $key) {
            $merged[$key] = $this->get($key);
        }

        return $merged;
    }

    /**
     * Upsert overrides. Keys are full namespaced keys; values are native
     * (encoded to JSON for storage).
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_by' => $userId],
            );
        }

        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
