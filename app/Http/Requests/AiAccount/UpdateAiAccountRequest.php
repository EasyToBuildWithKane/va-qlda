<?php

namespace App\Http\Requests\AiAccount;

use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('aiAccount'));
    }

    public function rules(): array
    {
        return [
            'email_registered' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'notify_before_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('password') && $this->user()->role !== SystemRole::Admin) {
                $validator->errors()->add('password', 'Chỉ quản trị viên được cập nhật mật khẩu.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'email_registered.required' => 'Vui lòng nhập email đăng ký.',
            'email_registered.email' => 'Email đăng ký không hợp lệ.',
        ];
    }
}
