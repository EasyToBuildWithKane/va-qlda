<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\ProjectCategory;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    protected function prepareForValidation(): void
    {
        $allowed = Region::values();
        $scopeRegions = $this->input('scope_regions');
        if (is_array($scopeRegions)) {
            $this->merge([
                'scope_regions' => array_values(array_filter(
                    $scopeRegions,
                    fn ($v) => is_string($v) && in_array($v, $allowed, true)
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        $id = $project->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('projects', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(ProjectStatus::values())],
            'type' => ['required', Rule::in(ProjectType::values())],
            'category' => ['nullable', Rule::in(ProjectCategory::values())],
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
            'department_id.exists' => 'Phòng ban phụ trách không hợp lệ hoặc đã ngừng hoạt động. Vui lòng chọn lại.',
            'scope_departments.*.exists' => 'Có phòng ban trong phạm vi không còn tồn tại hoặc đã ngừng hoạt động — hãy chọn lại.',
            'manager_id.exists' => 'Chủ dự án (nhân sự) không tồn tại hoặc đã bị xóa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên dự án',
            'department_id' => 'phòng ban phụ trách',
            'scope_departments.*' => 'phòng ban áp dụng',
            'manager_id' => 'chủ dự án',
        ];
    }
}
