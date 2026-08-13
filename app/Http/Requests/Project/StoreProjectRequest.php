<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Support\Enums\ProjectCategory;
use App\Support\Enums\ProjectScope;
use App\Support\Enums\ProjectStatus;
use App\Support\Enums\ProjectType;
use App\Support\Enums\Region;
use App\Support\Project\ProjectDepartmentPayload;
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
        $departments = ProjectDepartmentPayload::normalize(
            $this->input('department_id'),
            $this->input('scope_departments'),
            fallbackOwner: true,
        );

        $this->merge([
            'department_id' => $departments['department_id'],
            'scope_departments' => $departments['scope_departments'],
            'scope_regions' => is_array($scopeRegions = $this->input('scope_regions'))
                ? array_values(array_filter(
                    $scopeRegions,
                    fn ($v) => is_string($v) && in_array($v, Region::values(), true)
                ))
                : $this->input('scope_regions'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Mã thực tế do CreateProjectUseCase cấp phát khi lưu (form chỉ hiển thị gợi ý).
            'code' => ['nullable', 'string', 'max:30'],
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
            'scope_departments.*' => [
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'actual_budget' => ['nullable', 'numeric', 'min:0'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
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
            'type.required' => 'Vui lòng chọn loại dự án.',
            'scope.required' => 'Phải chọn phạm vi áp dụng.',
            'budget.min' => 'Ngân sách phải lớn hơn hoặc bằng 0.',
            'actual_budget.min' => 'Ngân sách thực tế phải lớn hơn hoặc bằng 0.',
            'due_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'department_id.exists' => 'Phòng ban phụ trách không hợp lệ hoặc đã ngừng hoạt động. Vui lòng chọn lại ở tab «Phạm vi».',
            'department_id.integer' => 'Phòng ban phụ trách không hợp lệ.',
            'scope_departments.*.exists' => 'Có phòng ban liên đới không còn tồn tại hoặc đã ngừng hoạt động — hãy bỏ chọn và chọn lại.',
            'scope_departments.*.integer' => 'Danh sách phòng ban liên đới không hợp lệ.',
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
            'scope_departments' => 'phòng ban liên đới',
            'scope_departments.*' => 'phòng ban liên đới',
            'manager_id' => 'chủ dự án',
        ];
    }
}
