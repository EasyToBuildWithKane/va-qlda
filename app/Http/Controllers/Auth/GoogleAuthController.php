<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Providers\RouteServiceProvider;
use App\Services\Cms\CmsEmployeeSyncService;
use App\Services\Cms\SystemAccountProvisioner;
use App\Support\Auth\LoginRedirectSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Đăng nhập Google chưa được cấu hình trên máy chủ.');
        }

        $request->session()->put(
            'login.redirect',
            LoginRedirectSanitizer::sanitize($request->query('redirect')),
        );

        /** @var \Laravel\Socialite\Two\GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->with('error', 'Không thể xác thực với Google. Vui lòng thử lại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            return redirect()->route('login')
                ->with('error', 'Tài khoản Google không có email. Không thể đăng nhập.');
        }

        if (! $this->emailDomainAllowed($email)) {
            return redirect()->route('login')
                ->with('error', 'Email không thuộc tổ chức được phép đăng nhập.');
        }

        $employee = Employee::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($employee === null) {
            return redirect()->route('login')
                ->with('error', 'Email chưa được liên kết với nhân sự trong hệ thống.');
        }

        $employee = app(CmsEmployeeSyncService::class)->refreshEmployeeIfLinked($employee);

        $googleAvatar = trim((string) $googleUser->getAvatar());
        if ($googleAvatar !== '' && blank($employee->avatar_path)) {
            $employee->forceFill(['avatar_path' => $googleAvatar])->save();
            $employee->refresh();
        }

        $account = SystemAccount::query()
            ->where('employee_id', $employee->id)
            ->first();

        if ($account === null) {
            if ($employee->cms_user_id === null) {
                return redirect()->route('login')
                    ->with('error', 'Chưa có tài khoản đăng nhập cho nhân sự này. Liên hệ quản trị.');
            }

            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        } elseif ($employee->cms_user_id !== null) {
            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        }

        if (! $account->is_active) {
            return redirect()->route('login')
                ->with('error', 'Tài khoản đăng nhập đã bị vô hiệu hóa. Liên hệ quản trị.');
        }

        Auth::guard('system')->login($account, true);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();

        $target = LoginRedirectSanitizer::sanitize(
            $request->session()->pull('login.redirect'),
            RouteServiceProvider::HOME,
        );

        return redirect()->to($target);
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function emailDomainAllowed(string $email): bool
    {
        $domains = config('va.google_allowed_domains', []);
        if ($domains === []) {
            return true;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = substr($email, $at + 1);

        return in_array($domain, $domains, true);
    }
}
