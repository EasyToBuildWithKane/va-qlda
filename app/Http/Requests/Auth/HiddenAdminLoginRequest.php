<?php

namespace App\Http\Requests\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class HiddenAdminLoginRequest extends LoginRequest
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Vui lòng nhập tên đăng nhập.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'username' => $this->input('username'),
            'password' => $this->input('password'),
            'is_active' => true,
        ];

        if (! Auth::guard('system')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            ]);
        }

        $account = Auth::guard('system')->user();
        if ($account === null || ! $account->isAdminTier()) {
            Auth::guard('system')->logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Tài khoản không có quyền quản trị. Liên hệ IT để được cấp quyền.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }
}
