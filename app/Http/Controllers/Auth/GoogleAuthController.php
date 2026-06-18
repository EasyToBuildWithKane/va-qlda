<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Cms\CmsEmployeeSyncService;
use App\Services\Cms\SystemAccountProvisioner;
use App\Support\Auth\CoachingOnlyAccess;
use App\Support\Auth\LoginRedirectSanitizer;
use App\Support\Auth\PortalDestination;
use App\Support\Auth\TechLoginAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $portal = $this->normalizePortal($request->query('portal'));

        if (! $this->googleConfigured()) {
            return redirect()->route($this->loginRouteName($portal))
                ->with('error', 'Đăng nhập Google chưa được cấu hình trên máy chủ.');
        }

        $request->session()->put('login.portal', $portal);
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
        $portal = $this->normalizePortal($request->session()->pull('login.portal', 'portal'));
        $loginRoute = $this->loginRouteName($portal);

        if (! $this->googleConfigured()) {
            return redirect()->route($loginRoute)
                ->with('error', 'Đăng nhập Google chưa được cấu hình.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route($loginRoute)
                ->with('error', 'Không thể xác thực với Google. Vui lòng thử lại.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            return redirect()->route($loginRoute)
                ->with('error', 'Tài khoản Google không có email. Không thể đăng nhập.');
        }

        if ($portal === 'tech') {
            if (! TechLoginAccess::isAllowedEmail($email)) {
                return redirect()->route($loginRoute)
                    ->with('error', 'Email không được phép đăng nhập cổng Công nghệ.');
            }
        } elseif (! $this->emailAllowed($email)) {
            return redirect()->route($loginRoute)
                ->with('error', 'Email không thuộc tổ chức được phép đăng nhập.');
        }

        $employee = Employee::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($employee === null) {
            return redirect()->route($loginRoute)
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
                return redirect()->route($loginRoute)
                    ->with('error', 'Chưa có tài khoản đăng nhập cho nhân sự này. Liên hệ quản trị.');
            }

            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        } elseif ($employee->cms_user_id !== null) {
            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        }

        if (! $account->is_active) {
            return redirect()->route($loginRoute)
                ->with('error', 'Tài khoản đăng nhập đã bị vô hiệu hóa. Liên hệ quản trị.');
        }

        Auth::guard('system')->login($account, true);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();

        \App\Support\SecurityAuditLogger::login($account, "google:{$portal}");

        $target = LoginRedirectSanitizer::sanitize(
            $request->session()->pull('login.redirect'),
            PortalDestination::homePath($account, $portal),
        );

        return redirect()->to($target);
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function emailAllowed(string $email): bool
    {
        if (CoachingOnlyAccess::googleEmailAllowed($email)) {
            return true;
        }

        return $this->emailDomainAllowed($email);
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

    private function normalizePortal(?string $portal): string
    {
        return $portal === 'tech' ? 'tech' : 'portal';
    }

    private function loginRouteName(string $portal): string
    {
        return $portal === 'tech' ? 'tech.login' : 'login';
    }
}
