<?php

namespace App\Http\Requests\Settings;

use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reassign a SystemAccount's role. Super-admin only (the `roles.assign`
 * reserved ability, which no other role can ever hold).
 */
class AssignAccountRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->allows('roles.assign');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(SystemRole::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.in' => 'Vai trò không hợp lệ.',
        ];
    }
}
