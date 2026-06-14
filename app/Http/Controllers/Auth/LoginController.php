<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Auth\CoachingOnlyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the login screen.
     */
    public function create(Request $request): Response
    {
        $redirect = $request->query('redirect');

        return Inertia::render('Auth/Login', [
            'googleEnabled' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret')),
            'googleAuthUrl' => route('auth.google', array_filter([
                'redirect' => is_string($redirect) ? $redirect : null,
            ])),
        ]);
    }

    /**
     * Authenticate and start a session.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! config('va.password_login_enabled')) {
            abort(404);
        }

        $request->authenticate();

        $request->session()->regenerate();

        $account = Auth::guard('system')->user();
        $account->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(CoachingOnlyAccess::homePath($account));
    }

    /**
     * Log out and invalidate the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('system')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
