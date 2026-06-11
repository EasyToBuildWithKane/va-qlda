<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\SystemAccount;
use App\Models\SystemSetting;
use App\Support\Enums\SystemRole;
use App\Support\Navigation;
use App\Support\Settings\SettingsRepository;
use App\Support\Settings\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only system configuration (/settings). Thin controller: reads/writes
 * go through SettingsRepository; the schema drives both the payload and the
 * validation in UpdateSettingsRequest.
 */
class SystemSettingController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SystemSetting::class);

        return Inertia::render('Settings/Index', [
            'groups' => SettingsSchema::groups(),
            'settings' => $this->settingsPayload(),
            'permissions' => $this->permissionsPayload(),
            'can' => ['manage' => $request->user()->can('manage', SystemSetting::class)],
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $userId = $request->user()->id;

        DB::transaction(function () use ($request, $group, $userId) {
            $values = $group === 'permissions'
                ? [SettingsSchema::MATRIX_KEY => $this->normalizedGrants($request)]
                : $this->scalarValues($request, $group);

            $this->settings->setMany($values, $userId);
        });

        return back()->with('success', 'Đã lưu cấu hình hệ thống.');
    }

    /**
     * Per-group field list with the effective value (secrets masked).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function settingsPayload(): array
    {
        $out = [];

        foreach (['general', 'auth', 'telegram'] as $group) {
            $out[$group] = array_map(function (array $field): array {
                $value = $this->settings->get($field['key']);

                if ($field['secret']) {
                    return array_merge($field, ['value' => '', 'has_value' => filled($value)]);
                }

                return array_merge($field, ['value' => $value]);
            }, SettingsSchema::fields($group));
        }

        return $out;
    }

    /**
     * Role × permission matrix + nav visibility per role (read context for UI).
     *
     * @return array<string, mixed>
     */
    private function permissionsPayload(): array
    {
        /** @var array<string, string> $catalog */
        $catalog = config('va_permissions.permissions', []);

        $navByRole = [];
        foreach (SystemRole::cases() as $role) {
            $account = new SystemAccount;
            $account->role = $role;

            $navByRole[$role->value] = collect(Navigation::for($account))
                ->flatMap(fn (array $g) => array_map(fn (array $i) => $i['label'], $g['items']))
                ->values()
                ->all();
        }

        return [
            'catalog' => collect($catalog)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
            ])->values()->all(),
            'roles' => SystemRole::options(),
            'grants' => $this->settings->get(SettingsSchema::MATRIX_KEY, []),
            'editableRoles' => SettingsSchema::EDITABLE_ROLES,
            'lockedRole' => SettingsSchema::LOCKED_ROLE,
            'navByRole' => $navByRole,
        ];
    }

    /**
     * Build full-key => value from validated input; skip blank secrets so an
     * empty field keeps the stored value.
     *
     * @return array<string, mixed>
     */
    private function scalarValues(UpdateSettingsRequest $request, string $group): array
    {
        $validated = $request->validated();
        $values = [];

        foreach (SettingsSchema::fields($group) as $field) {
            $name = $field['name'];
            if (! array_key_exists($name, $validated)) {
                continue;
            }

            $value = $validated[$name];

            if ($field['type'] === 'secret' && blank($value)) {
                continue;
            }
            if ($field['type'] === 'bool') {
                $value = (bool) $value;
            }
            if ($field['type'] === 'list') {
                $value = array_values(array_filter(
                    array_map('trim', (array) $value),
                    fn ($v) => $v !== '',
                ));
            }

            $values[$field['key']] = $value;
        }

        return $values;
    }

    /**
     * Normalise the submitted matrix: only editable roles from the client,
     * admin forced to full access.
     *
     * @return array<string, array<int, string>>
     */
    private function normalizedGrants(UpdateSettingsRequest $request): array
    {
        /** @var array<string, array<int, string>> $grants */
        $grants = $request->validated()['grants'] ?? [];

        $out = [];
        foreach (SettingsSchema::EDITABLE_ROLES as $role) {
            $out[$role] = array_values(array_unique($grants[$role] ?? []));
        }
        $out[SettingsSchema::LOCKED_ROLE] = ['*'];

        return $out;
    }
}
