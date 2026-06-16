<?php

namespace App\Http\Requests\Credential;

use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('credential'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'credential_type' => ['sometimes', 'required', Rule::in(CredentialType::values())],
            'system_category' => ['sometimes', 'required', Rule::in(CredentialCategory::values())],
            'login_url' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'login_password' => ['nullable', 'string', 'max:2000', 'confirmed'],
            'login_password_confirmation' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'owner_id' => ['nullable', 'integer', 'exists:system_accounts,id'],
            'environment' => ['sometimes', 'required', Rule::in(CredentialEnvironment::values())],
            'status' => ['sometimes', Rule::in(CredentialStatus::values())],
            'mfa_enabled' => ['boolean'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
            'recovery_phone' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
            'password_expires_at' => ['nullable', 'date'],
            'is_shared' => ['boolean'],
            'is_critical' => ['boolean'],
            'badges' => ['nullable', 'array'],
            'badges.*' => ['string', 'max:32'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'login_password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ];
    }
}
