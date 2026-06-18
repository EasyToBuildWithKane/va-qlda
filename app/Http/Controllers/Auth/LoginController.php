<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Auth\PortalDestination;
use App\Support\Auth\TechLoginAccess;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Portal login (/login) — all org users; post-login routes (e.g. /congnghe) later.
     */
    public function createPortal(Request $request): Response
    {
        return $this->renderLogin($request, 'portal');
    }

    /**
     * Tech QLDA login (/tech/login) — whitelist only.
     */
    public function createTech(Request $request): Response
    {
        return $this->renderLogin($request, 'tech');
    }

    public function storePortal(LoginRequest $request): RedirectResponse
    {
        return $this->storePasswordLogin($request, 'portal');
    }

    public function storeTech(LoginRequest $request): RedirectResponse
    {
        return $this->storePasswordLogin($request, 'tech');
    }

    /**
     * Log out and invalidate the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        SecurityAuditLogger::logout(Auth::guard('system')->user());

        Auth::guard('system')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tech.login');
    }

    private function renderLogin(Request $request, string $portal): Response
    {
        $redirect = $request->query('redirect');

        return Inertia::render('Auth/Login', [
            'googleEnabled' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret')),
            'googleAuthUrl' => route('auth.google', array_filter([
                'portal' => $portal,
                'redirect' => is_string($redirect) ? $redirect : null,
            ])),
        ]);
    }

    private function storePasswordLogin(LoginRequest $request, string $portal): RedirectResponse
    {
        if (! config('va.password_login_enabled')) {
            abort(404);
        }

        $request->authenticate();

        $request->session()->regenerate();

        $account = Auth::guard('system')->user();

        if ($portal === 'tech' && ! TechLoginAccess::isAllowedEmail($account->employee?->email)) {
            SecurityAuditLogger::techDenied($account, $account->employee?->email);

            Auth::guard('system')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Email nhân sự không được phép đăng nhập cổng Công nghệ.',
            ]);
        }

        $account->forceFill(['last_login_at' => now()])->save();

        SecurityAuditLogger::login($account, $portal);

        return redirect()->intended(PortalDestination::homePath($account, $portal));
    }
}
