<?php

namespace App\Http\Middleware;

use App\Models\Blocker;
use App\Services\NotificationService;
use App\Support\Auth\PortalDestination;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Navigation;
use App\Support\NavigationBadges;
use App\Support\Options;
use App\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version (busts client cache on deploy).
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default to every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $account = $request->user();

        return array_merge(parent::share($request), [
            'csrf_token' => fn () => csrf_token(),
            // App identity (admin-editable via /settings, overlaid onto config).
            'app' => [
                'name' => config('va.app_name'),
                'short_name' => config('va.app_short_name'),
                'version' => config('va.app_version'),
                'support_email' => config('va.support_email'),
            ],
            'auth' => [
                'user' => $account ? [
                    'id' => $account->id,
                    'username' => $account->username,
                    'display_name' => $account->display_name,
                    'email' => $account->employee?->email,
                    'role' => $account->role,
                    'is_super_admin' => $account->isSuperAdmin(),
                    'is_admin_tier' => $account->isAdminTier(),
                    // Resolved permission grants for this role (RBAC matrix);
                    // super_admin carries the wildcard ['*']. Frontend gates via
                    // usePermission().can('module.action').
                    'permissions' => $account->isSuperAdmin()
                        ? ['*']
                        : (array) (config('va_permissions.role_grants', [])[$account->role->value] ?? []),
                    'employee' => $account->employee ? [
                        'id' => $account->employee->id,
                        'full_name' => $account->employee->full_name,
                        'avatar_path' => PublicMediaUrl::fromPublicDisk($account->employee->avatar_path),
                    ] : null,
                    'employee_id' => $account->employee_id,
                ] : null,
            ],
            'portal' => fn () => $account ? [
                'canEnterWorkspace' => PortalDestination::canEnterWorkspace($account),
                'workspaceHome' => PortalDestination::workspaceHomePath($account),
            ] : [
                'canEnterWorkspace' => false,
                'workspaceHome' => '/dashboard',
            ],
            'realtime' => [
                'enabled' => (bool) config('realtime.enabled'),
                'url' => config('realtime.client_url'),
                'websocket' => (bool) config('realtime.websocket'),
            ],
            'nav' => $account ? NavigationBadges::decorate(Navigation::for($account), $account) : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'created_blocker_id' => fn () => $request->session()->get('created_blocker_id'),
                'created_blocker_ids' => fn () => $request->session()->get('created_blocker_ids'),
                'created_vendor' => fn () => $request->session()->get('created_vendor'),
                'created_contract_id' => fn () => $request->session()->get('created_contract_id'),
            ],
            'notifications' => fn () => $account ? [
                'unread_count' => app(NotificationService::class)->unreadCount($account),
            ] : ['unread_count' => 0],
            'onboarding' => fn () => $account
                ? app(\App\Services\OnboardingService::class)->payload($account)
                : null,
            'quickBlocker' => fn () => $account ? [
                'canReport' => Gate::forUser($account)->allows('create', Blocker::class),
                'projects' => Options::projects()->values()->all(),
                'employees' => Options::employees()->values()->all(),
                'enums' => [
                    'blockerSeverity' => BlockerSeverity::options(),
                    'blockerStatus' => BlockerStatus::options(),
                ],
            ] : null,
        ]);
    }
}
