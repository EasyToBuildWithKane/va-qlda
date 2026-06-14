<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\HiddenAdminLoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password login at a non-linked URL (production Google-only UI).
 */
class HiddenAdminLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/HiddenAdminLogin');
    }

    public function store(HiddenAdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $account = Auth::guard('system')->user();
        $account->forceFill(['last_login_at' => now()])->save();

        $request->session()->forget('url.intended');

        return redirect()->to(RouteServiceProvider::HOME);
    }
}
