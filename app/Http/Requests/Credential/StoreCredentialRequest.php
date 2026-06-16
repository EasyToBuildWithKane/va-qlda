<?php

namespace App\Http\Requests\Credential;

use App\Models\Credential;
use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Credential::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'credential_type' => ['required', Rule::in(CredentialType::values())],
            'system_category' => ['required', Rule::in(CredentialCategory::values())],
            'login_url' => ['nullable', 'string', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'login_password' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'owner_id' => ['nullable', 'integer', 'exists:system_accounts,id'],
            'environment' => ['required', Rule::in(CredentialEnvironment::values())],
            'status' => ['nullable', Rule::in(CredentialStatus::values())],
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
            'name.required' => 'Nhập tên tài khoản.',
            'credential_type.required' => 'Chọn loại tài khoản.',
            'system_category.required' => 'Chọn hệ thống / danh mục.',
        ];
    }
}
