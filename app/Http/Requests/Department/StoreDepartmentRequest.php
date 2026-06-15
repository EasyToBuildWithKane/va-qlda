<?php

namespace App\Http\Requests\Department;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Department::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'member_ids' => ['nullable', 'array', 'max:200'],
            'member_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_ids.max' => 'Tối đa 200 thành viên mỗi phòng ban.',
            'member_ids.*.exists' => 'Thành viên không hợp lệ.',
        ];
    }
}
