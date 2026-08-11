<?php

namespace App\Http\Requests\AiAccount;

use App\Support\Enums\AiAccountPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantAiAccountAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageAccess', $this->route('aiAccount'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:system_accounts,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(AiAccountPermission::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => 'Chọn ít nhất một quyền.',
            'account_id.required' => 'Vui lòng chọn người được cấp quyền.',
        ];
    }
}
