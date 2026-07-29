<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SystemAccount;
use App\Services\Hrm\HrmIdentityResolver;
use App\Services\Hrm\HrmSsoJwtVerifier;
use App\Services\Hrm\SystemAccountProvisioner;
use App\Support\Auth\LoginRedirectSanitizer;
use App\Support\Auth\PortalDestination;
use App\Support\Auth\TechLoginAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SSO HRM → QLDA: user đăng nhập Google một lần trên HRM (IdP nội bộ);
 * QLDA redirect sang {HRM}/sso/authorize và nhận JWT RS256 về callback —
 * không gọi Google OAuth riêng trong luồng này (ADR-013 phía va-hrm).
 */
class HrmSsoController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $portal = $this->normalizePortal($request->query('portal'));

        if (! $this->ssoConfigured()) {
            return redirect()->route($this->loginRouteName($portal))
                ->with('error', 'Đăng nhập qua HRM chưa được cấu hình trên máy chủ.');
        }

        $state = Str::random(40);

        $request->session()->put('login.portal', $portal);
        $request->session()->put('login.hrm_state', $state);
        $request->session()->put(
            'login.redirect',
            LoginRedirectSanitizer::sanitize($request->query('redirect')),
        );

        $query = http_build_query([
            'client_id' => (string) config('services.hrm_sso.client_id'),
            'redirect_uri' => $this->callbackUrl(),
            'state' => $state,
        ]);

        return redirect()->away(
            config('services.hrm_sso.base_url').'/sso/authorize?'.$query,
        );
    }

    public function callback(Request $request, HrmSsoJwtVerifier $verifier): RedirectResponse
    {
        $portal = $this->normalizePortal($request->session()->pull('login.portal', 'portal'));
        $loginRoute = $this->loginRouteName($portal);

        if (! $this->ssoConfigured()) {
            return redirect()->route($loginRoute)
                ->with('error', 'Đăng nhập qua HRM chưa được cấu hình.');
        }

        $expectedState = (string) $request->session()->pull('login.hrm_state', '');
        $state = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->route($loginRoute)
                ->with('error', 'Phiên đăng nhập không hợp lệ. Vui lòng thử lại.');
        }

        $token = (string) $request->query('token', '');
        if ($token === '') {
            return redirect()->route($loginRoute)
                ->with('error', 'HRM không trả về token đăng nhập. Vui lòng thử lại.');
        }

        try {
            $claims = $verifier->verify($token);
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route($loginRoute)
                ->with('error', 'Không xác thực được phiên HRM. Vui lòng thử lại.');
        }

        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($email === '') {
            return redirect()->route($loginRoute)
                ->with('error', 'Token HRM không có email. Không thể đăng nhập.');
        }

        if ($portal === 'tech' && ! TechLoginAccess::isAllowedEmail($email)) {
            return redirect()->route($loginRoute)
                ->with('error', 'Email không được phép đăng nhập cổng Công nghệ.');
        }

        $employeeUuid = trim((string) ($claims['employee_uuid'] ?? ''));

        $employee = $this->resolveEmployee($email, $employeeUuid !== '' ? $employeeUuid : null);

        if ($employee === null) {
            return redirect()->route($loginRoute)
                ->with('error', 'Email chưa có trong hệ thống nhân sự (HRM). Liên hệ quản trị.');
        }

        if (! $employee->is_active) {
            return redirect()->route($loginRoute)
                ->with('error', 'Nhân sự đã ngừng hoạt động trong hệ thống. Liên hệ quản trị.');
        }

        if ($employeeUuid !== '' && $employee->hrm_employee_uuid !== $employeeUuid) {
            $employee->forceFill(['hrm_employee_uuid' => $employeeUuid])->save();
            $employee->refresh();
        }

        $account = SystemAccount::query()
            ->where('employee_id', $employee->id)
            ->first();

        if ($account === null || $employee->hrm_user_id !== null || $employee->hrm_employee_uuid !== null) {
            $account = app(SystemAccountProvisioner::class)->ensureForEmployee($employee);
        }

        if (! $account->is_active) {
            return redirect()->route($loginRoute)
                ->with('error', 'Tài khoản đăng nhập đã bị vô hiệu hóa. Liên hệ quản trị.');
        }

        Auth::guard('system')->login($account, true);
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();

        \App\Support\SecurityAuditLogger::login($account, "hrm-sso:{$portal}");

        $target = LoginRedirectSanitizer::sanitize(
            $request->session()->pull('login.redirect'),
            PortalDestination::homePath($account, $portal),
        );

        return redirect()->to($target);
    }

    /**
     * Ưu tiên khớp `hrm_employee_uuid` (claim JWT); sau đó email trên QLDA;
     * cuối cùng lazy upsert từ HRM Public API như luồng Google.
     */
    private function resolveEmployee(string $email, ?string $employeeUuid): ?Employee
    {
        $resolver = app(HrmIdentityResolver::class);

        if ($employeeUuid !== null) {
            $employee = Employee::query()
                ->where('hrm_employee_uuid', $employeeUuid)
                ->first();

            if ($employee !== null) {
                return $resolver->refreshEmployeeIfLinked($employee);
            }
        }

        $employee = Employee::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($employee !== null) {
            return $resolver->refreshEmployeeIfLinked($employee);
        }

        return $resolver->ensureEmployeeByEmail($email);
    }

    private function ssoConfigured(): bool
    {
        return (bool) config('services.hrm_sso.enabled')
            && filled(config('services.hrm_sso.base_url'));
    }

    /** Redirect URI cố định {APP_URL}/auth/hrm/callback — whitelist tuyệt đối trên HRM. */
    private function callbackUrl(): string
    {
        return route('auth.hrm.callback');
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
