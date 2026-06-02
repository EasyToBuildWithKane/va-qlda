<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Illuminate\Http\Request;
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
            'auth' => [
                'user' => $account ? [
                    'id' => $account->id,
                    'username' => $account->username,
                    'display_name' => $account->display_name,
                    'role' => $account->role,
                    'employee' => $account->employee ? [
                        'id' => $account->employee->id,
                        'full_name' => $account->employee->full_name,
                        'avatar_path' => $account->employee->avatar_path,
                    ] : null,
                ] : null,
            ],
            'nav' => $account ? Navigation::for($account) : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
