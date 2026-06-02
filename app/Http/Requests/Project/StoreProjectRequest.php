<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Support\Options;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('department_id')) {
            $this->merge([
                'department_id' => Options::defaultOwnerDepartmentId(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('projects', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(ProjectStatus::values())],
            'type' => ['required', Rule::in(ProjectType::values())],
            'scope' => ['required', Rule::in(ProjectScope::values())],
            'scope_regions' => ['nullable', 'array'],
            'scope_regions.*' => [Rule::in(Region::values())],
            'scope_departments' => ['nullable', 'array'],
            'scope_departments.*' => ['integer', 'exists:departments,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'actual_budget' => ['nullable', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên dự án không được để trống.',
            'code.required' => 'Mã dự án không được để trống.',
            'code.unique' => 'Mã dự án đã tồn tại.',
            'type.required' => 'Vui lòng chọn loại dự án.',
            'scope.required' => 'Phải chọn phạm vi áp dụng.',
            'budget.min' => 'Ngân sách phải lớn hơn hoặc bằng 0.',
            'actual_budget.min' => 'Ngân sách thực tế phải lớn hơn hoặc bằng 0.',
            'due_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        ];
    }
}
