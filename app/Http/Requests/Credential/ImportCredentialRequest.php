<?php

namespace App\Http\Requests\Credential;

use App\Models\Credential;
use App\Support\Enums\CredentialCategory;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialStatus;
use App\Support\Enums\CredentialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportCredentialRequest extends FormRequest
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
            'overwrite' => ['sometimes', 'boolean'],
            'rows' => ['required', 'array', 'max:200'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.credential_type' => ['required', Rule::in(CredentialType::values())],
            'rows.*.system_category' => ['required', Rule::in(CredentialCategory::values())],
            'rows.*.username' => ['nullable', 'string', 'max:255'],
            'rows.*.login_url' => ['nullable', 'string', 'max:2048'],
            'rows.*.provider_name' => ['nullable', 'string', 'max:255'],
            'rows.*.environment' => ['nullable', Rule::in(CredentialEnvironment::values())],
            'rows.*.status' => ['nullable', Rule::in(CredentialStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.max' => 'Mỗi lần nhập tối đa 200 dòng.',
            'overwrite.boolean' => 'Tùy chọn ghi đè không hợp lệ.',
        ];
    }
}
