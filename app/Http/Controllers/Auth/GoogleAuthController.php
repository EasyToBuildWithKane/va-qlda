<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Hrm\HrmIdentityResolver;
use App\Services\Hrm\SystemAccountProvisioner;
use App\Support\Auth\LoginRedirectSanitizer;
use App\Support\Auth\PortalDestination;
use App\Support\Auth\TechLoginAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
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
        // Giữ session trước khi nhảy sang Google (cross-site OAuth).
        $request->session()->save();

        /** @var \Laravel\Socialite\Two\GoogleProvider $google */
        $google = Socialite::driver('google');

        // redirect_uri phải khớp tuyệt đối với Google Cloud Console + callback dưới đây.
        // - prompt=select_account: một màn chọn tài khoản (không bọc thêm AccountChooser — tránh double chooser)
        // - hd: gợi ý Google Workspace nhà trường trên UI Google
        $params = ['prompt' => 'select_account'];
        $hostedDomain = strtolower(trim((string) config('services.google.hosted_domain', '')));
        if ($hostedDomain !== '') {
            $params['hd'] = $hostedDomain;
        }

        return $google
            ->scopes(['openid', 'profile', 'email'])
            ->redirectUrl($this->callbackUrl())
            ->with($params)
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

        if ($request->filled('error')) {
            return redirect()->route($loginRoute)
                ->with('error', 'Bạn đã hủy đăng nhập bằng Google.');
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->callbackUrl())
                ->user();
        } catch (InvalidStateException $e) {
            Log::warning('Google OAuth invalid state', [
                'message' => $e->getMessage(),
                'host' => $request->getHost(),
                'redirect' => $this->callbackUrl(),
            ]);

            // Session cookie mất giữa redirect↔callback (host lệch APP_URL, SameSite, …).
            try {
                $googleUser = Socialite::driver('google')
                    ->redirectUrl($this->callbackUrl())
                    ->stateless()
                    ->user();
            } catch (\Throwable $fallback) {
                Log::warning('Google OAuth stateless fallback failed', [
                    'message' => $fallback->getMessage(),
                ]);

                return redirect()->route($loginRoute)
                    ->with('error', 'Phiên đăng nhập đã hết hạn. Mở lại trang login trên đúng địa chỉ APP_URL rồi thử lại.');
            }
        } catch (\Throwable $e) {
            Log::warning('Google OAuth failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'host' => $request->getHost(),
                'redirect' => $this->callbackUrl(),
            ]);

            $hint = 'Không thể xác thực với Google. Vui lòng thử lại.';
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'redirect_uri') || str_contains($msg, 'redirect uri')) {
                $hint = 'redirect_uri Google không khớp. Thêm đúng URI vào Google Cloud Console: '.$this->callbackUrl();
            }

            return redirect()->route($loginRoute)->with('error', $hint);
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

        try {
            return $this->completeGoogleLogin($request, $googleUser, $email, $portal, $loginRoute);
        } catch (\Throwable $e) {
            // Luôn ghi stderr/PHP log — kể cả khi storage/logs không ghi được.
            error_log('[auth.google.callback_failed] '.$e::class.': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());

            try {
                report($e);
                Log::error('auth.google.callback_failed', [
                    'email' => $email,
                    'portal' => $portal,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ]);
            } catch (\Throwable) {
                // bỏ qua — đã error_log ở trên
            }

            return redirect()->route($loginRoute)
                ->with('error', 'Đăng nhập Google thất bại trên máy chủ ('.$e::class.'). Kiểm tra log auth.google.callback_failed.');
        }
    }

    private function completeGoogleLogin(
        Request $request,
        mixed $googleUser,
        string $email,
        string $portal,
        string $loginRoute,
    ): RedirectResponse {
        $resolver = app(HrmIdentityResolver::class);

        $employee = Employee::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($employee === null) {
            // SSOT: chưa có trên Workspace → tra HRM Public API và lazy upsert.
            if (! $resolver->isApiConfigured()) {
                Log::warning('auth.google.hrm_api_not_configured', ['email' => $email]);

                return redirect()->route($loginRoute)
                    ->with('error', 'Máy chủ chưa cấu hình kết nối HRM (HRM_API_TOKEN). Liên hệ quản trị.');
            }

            $employee = $resolver->ensureEmployeeByEmail($email);

            if ($employee === null) {
                Log::warning('auth.google.hrm_employee_missing', ['email' => $email]);

                return redirect()->route($loginRoute)
                    ->with('error', 'Email chưa có trong hệ thống nhân sự (HRM) hoặc chưa ở trạng thái active. Liên hệ quản trị.');
            }
        } else {
            $employee = $resolver->refreshEmployeeIfLinked($employee);
        }

        if (! $employee->is_active) {
            return redirect()->route($loginRoute)
                ->with('error', 'Nhân sự đã ngừng hoạt động trong hệ thống. Liên hệ quản trị.');
        }

        $googleAvatar = trim((string) $googleUser->getAvatar());
        if ($googleAvatar !== '' && blank($employee->avatar_path)) {
            $employee->forceFill(['avatar_path' => $googleAvatar])->save();
            $employee->refresh();
        }

        $account = SystemAccount::query()
            ->where('employee_id', $employee->id)
            ->first();

        // Khớp HrmSsoController: provision khi chưa có account, hoặc khi đã liên kết HRM
        // (hrm_user_id và/hoặc hrm_employee_uuid — API-first có thể chỉ có uuid).
        if ($account === null || $employee->hrm_user_id !== null || $employee->hrm_employee_uuid !== null) {
            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        }

        if ($account === null) {
            Log::error('auth.google.account_missing_after_provision', [
                'email' => $email,
                'employee_id' => $employee->id,
            ]);

            return redirect()->route($loginRoute)
                ->with('error', 'Không tạo được tài khoản đăng nhập. Liên hệ quản trị.');
        }

        if (! $account->is_active) {
            return redirect()->route($loginRoute)
                ->with('error', 'Tài khoản đăng nhập đã bị vô hiệu hóa. Liên hệ quản trị.');
        }

        Auth::guard('system')->login($account, true);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();

        try {
            \App\Support\SecurityAuditLogger::login($account, "google:{$portal}");
        } catch (\Throwable $e) {
            // Không chặn đăng nhập vì bảng audit thiếu / lỗi ghi.
            error_log('[auth.google.audit_failed] '.$e->getMessage());
            try {
                report($e);
                Log::warning('auth.google.audit_failed', [
                    'email' => $email,
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
                // ignore
            }
        }

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

    /** URI tuyệt đối gửi Google — phải whitelist trong Google Cloud Console. */
    private function callbackUrl(): string
    {
        $configured = trim((string) config('services.google.redirect'));
        if ($configured !== '') {
            return $configured;
        }

        return route('auth.google.callback');
    }

    private function emailAllowed(string $email): bool
    {
        $allowedEmails = config('va.google_allowed_emails', []);
        if (is_array($allowedEmails)) {
            $normalized = array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $allowedEmails,
            );
            if (in_array(strtolower(trim($email)), $normalized, true)) {
                return true;
            }
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

        $domain = strtolower(substr($email, $at + 1));

        foreach ($domains as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed === '') {
                continue;
            }

            // Exact match hoặc subdomain (vd. hcm.vaschools.edu.vn khi allow vaschools.edu.vn).
            if ($domain === $allowed || str_ends_with($domain, '.'.$allowed)) {
                return true;
            }
        }

        return false;
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
