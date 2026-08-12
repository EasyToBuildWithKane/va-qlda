<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SendTestEmailTemplateRequest;
use App\Http\Requests\Settings\UpdateEmailTemplateRequest;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Mail\EmailTemplateTestMail;
use App\Models\EmailTemplate;
use App\Models\SystemAccount;
use App\Models\SystemSetting;
use App\Services\OnboardingService;
use App\Support\Auth\PermissionCatalog;
use App\Support\Enums\SystemRole;
use App\Support\Mail\EmailTemplateDefaults;
use App\Support\Mail\EmailTemplateSampleVars;
use App\Support\Mail\EmailTemplateSnippets;
use App\Support\Navigation;
use App\Support\SecurityAuditLogger;
use App\Support\Settings\SettingsRepository;
use App\Support\Settings\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    public function index(Request $request, ?string $group = null): Response
    {
        $this->authorize('viewAny', SystemSetting::class);

        $activeGroup = \in_array($group, SettingsSchema::GROUPS, true)
            ? $group
            : SettingsSchema::GROUPS[0];

        return Inertia::render('Settings/Index', [
            'groups' => SettingsSchema::groups(),
            'activeGroup' => $activeGroup,
            'settings' => $this->settingsPayload(),
            'emailTemplates' => $this->emailTemplatesPayload(),
            'emailPreviewBrand' => (string) ($this->settings->get('email.from_name') ?: config('va.app_name', 'VAschools Workspace')),
            'emailTestRecipient' => $this->testEmailRecipient($request->user()),
            'permissions' => $this->permissionsPayload(),
            'accounts' => $activeGroup === 'accounts'
                ? $this->accountsPayload($request->user())
                : ['accounts' => [], 'roles' => []],
            'menu' => $activeGroup === 'menu'
                ? $this->menuPayload()
                : ['groups' => [], 'hidden' => []],
            'can' => ['manage' => $request->user()->can('manage', SystemSetting::class)],
        ]);
    }

    /**
     * Active login accounts + assignable roles for the "Tài khoản & Vai trò"
     * tab (super-admin only). Used to reassign a SystemAccount's role at runtime.
     *
     * @return array<string, mixed>
     */
    private function accountsPayload(SystemAccount $current): array
    {
        $accounts = SystemAccount::query()
            ->with('employee:id,full_name,email,avatar_path')
            ->orderByDesc('is_active')
            ->orderBy('display_name')
            ->get()
            ->map(fn (SystemAccount $a) => [
                'id' => $a->id,
                'username' => $a->username,
                'display_name' => $a->display_name,
                'email' => $a->employee?->email,
                'role' => $a->role->value,
                'is_active' => $a->is_active,
                'is_self' => $a->id === $current->id,
            ])
            ->all();

        return [
            'accounts' => $accounts,
            'roles' => SystemRole::options(),
        ];
    }

    /**
     * Menu-visibility tab (super-admin only): the full group catalog plus the
     * keys currently hidden system-wide. MenuTab renders one toggle per group.
     *
     * @return array{groups:array<int, array<string, mixed>>, hidden:array<int, string>}
     */
    private function menuPayload(): array
    {
        return [
            'groups' => Navigation::groupCatalog(),
            'hidden' => array_values(array_map('strval', (array) $this->settings->get('menu.hidden_groups', []))),
        ];
    }

    public function emailTemplates(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', SystemSetting::class);

        return response()->json(['templates' => $this->emailTemplatesPayload()]);
    }

    public function updateEmailTemplate(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $emailTemplate, $request) {
            $emailTemplate->update([
                'subject' => $validated['subject'],
                'body_html' => $validated['body_html'],
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => $request->user()->id,
            ]);
        });

        SecurityAuditLogger::emailTemplateUpdated($request->user(), $emailTemplate->id, $emailTemplate->key);

        return back()->with('success', 'Đã lưu mẫu email.');
    }

    public function resetEmailTemplate(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $defaults = EmailTemplateDefaults::forKey($emailTemplate->key);
        if ($defaults['subject'] === '' && $defaults['body_html'] === '') {
            return back()->with('error', 'Không có mẫu mặc định cho loại email này.');
        }

        DB::transaction(function () use ($defaults, $emailTemplate, $request) {
            $emailTemplate->update([
                'subject' => $defaults['subject'],
                'body_html' => $defaults['body_html'],
                'updated_by' => $request->user()->id,
            ]);
        });

        SecurityAuditLogger::emailTemplateUpdated($request->user(), $emailTemplate->id, $emailTemplate->key, reset: true);

        return back()->with('success', 'Đã khôi phục mẫu email chuẩn.');
    }

    public function sendTestEmailTemplate(SendTestEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $validated = $request->validated();
        $vars = EmailTemplateSampleVars::forKey($emailTemplate->key);
        $rendered = EmailTemplate::renderDeliveryFromParts(
            $validated['subject'],
            $validated['body_html'],
            $vars,
        );

        try {
            Mail::to($validated['email'])->send(new EmailTemplateTestMail(
                $rendered['subject'],
                $rendered['html'],
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Không gửi được email thử. Kiểm tra cấu hình MAIL_* trên server.');
        }

        return back()->with('success', 'Đã gửi email thử tới '.$validated['email'].'.');
    }

    /** Super-admin action: reset the full-screen Welcome onboarding so every account sees it again. */
    public function resetOnboardingWelcome(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $affected = $onboarding->resetWelcomeForAll();

        SecurityAuditLogger::onboarding($request->user(), 'welcome_reset', null, ['affected' => $affected]);

        return back()->with('success', "Đã đặt lại — {$affected} tài khoản sẽ thấy màn hình chào mừng ở lần đăng nhập tới.");
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        $this->authorize('manage', SystemSetting::class);

        $user = $request->user();

        $values = $group === 'permissions'
            ? [SettingsSchema::MATRIX_KEY => $this->normalizedGrants($request)]
            : $this->scalarValues($request, $group);

        DB::transaction(function () use ($values, $user) {
            $this->settings->setMany($values, $user->id);
        });

        if ($group === 'permissions') {
            SecurityAuditLogger::permissionMatrixUpdated($user);
        } else {
            SecurityAuditLogger::settingsUpdated($user, $group, array_keys($values));
        }

        return back()->with('success', 'Đã lưu cấu hình hệ thống.');
    }

    /**
     * Per-group field list with the effective value (secrets masked).
     * Derived from SettingsSchema::GROUPS so adding a new group here is automatic.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function settingsPayload(): array
    {
        $out = [];

        $scalarGroups = array_filter(
            SettingsSchema::GROUPS,
            static fn (string $g): bool => $g !== 'permissions',
        );

        foreach ($scalarGroups as $group) {
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
     * @return array<int, array<string, mixed>>
     */
    private function emailTemplatesPayload(): array
    {
        return EmailTemplate::query()
            ->with('updatedBy')
            ->orderBy('id')
            ->get()
            ->map(fn (EmailTemplate $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'name' => $t->name,
                'subject' => $t->subject,
                'body_html' => $t->body_html,
                'is_active' => $t->is_active,
                'variables' => EmailTemplate::variableMeta($t->key),
                'default_subject' => EmailTemplateDefaults::forKey($t->key)['subject'],
                'default_body_html' => EmailTemplateDefaults::forKey($t->key)['body_html'],
                'snippets' => EmailTemplateSnippets::forKey($t->key),
                'updated_at' => $t->updated_at?->locale('vi')->diffForHumans(),
                'updated_by_name' => $t->updatedBy?->display_name,
            ])
            ->all();
    }

    /**
     * Role × permission matrix grouped by module, plus nav visibility per role
     * (read context for the redesigned permissions UI).
     *
     * @return array<string, mixed>
     */
    private function permissionsPayload(): array
    {
        $navByRole = [];
        foreach (SystemRole::cases() as $role) {
            $account = new SystemAccount;
            $account->role = $role;

            $navByRole[$role->value] = collect(Navigation::for($account))
                ->flatMap(fn (array $g) => array_map(fn (array $i) => $i['label'], $g['items']))
                ->values()
                ->all();
        }

        // Module-grouped abilities for the card UI.
        $modules = [];
        foreach (PermissionCatalog::modules() as $key => $def) {
            $abilities = [];
            foreach ($def['abilities'] as $ability => $label) {
                $fullKey = "{$key}.{$ability}";
                $abilities[] = [
                    'key' => $fullKey,
                    'action' => $ability,
                    'label' => $label,
                    'reserved' => PermissionCatalog::isReserved($fullKey),
                ];
            }
            $modules[] = [
                'key' => $key,
                'label' => $def['label'],
                'icon' => $def['icon'],
                'group' => $def['group'],
                'reserved' => collect($abilities)->every(fn (array $a) => $a['reserved']),
                'abilities' => $abilities,
            ];
        }

        return [
            'modules' => $modules,
            'catalog' => collect(PermissionCatalog::labels())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()->all(),
            'roles' => SystemRole::options(),
            'grants' => config('va_permissions.role_grants', []),
            'editableRoles' => SettingsSchema::EDITABLE_ROLES,
            'lockedRole' => SettingsSchema::LOCKED_ROLE,
            'reservedKeys' => PermissionCatalog::reservedKeys(),
            'standardActions' => PermissionCatalog::STANDARD_ACTIONS,
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
            // Reserved keys (system config, permission matrix, role assign) are
            // super-admin only — never persist them for an editable role.
            $out[$role] = PermissionCatalog::withoutReserved(
                array_values(array_unique($grants[$role] ?? [])),
            );
        }
        $out[SettingsSchema::LOCKED_ROLE] = ['*'];

        return $out;
    }

    private function testEmailRecipient(SystemAccount $user): ?string
    {
        $user->loadMissing('employee');
        $email = trim((string) ($user->employee?->email ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
