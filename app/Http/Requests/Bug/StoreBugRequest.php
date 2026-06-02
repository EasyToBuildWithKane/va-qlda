<?php

namespace App\Http\Requests\Bug;

use App\Models\Bug;
use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Bug::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->input('project_id'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:10000'],
            'expected' => ['nullable', 'string', 'max:5000'],
            'actual' => ['nullable', 'string', 'max:5000'],
            'environment' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(BugSeverity::values())],
            'priority' => ['required', Rule::in(TaskPriority::values())],
            'status' => ['nullable', Rule::in(BugStatus::values())],
            'reporter_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reporter_name' => ['nullable', 'required_without:reporter_employee_id', 'string', 'max:120'],
            'reporter_email' => ['nullable', 'email', 'max:160'],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'reporter_name.required_without' => 'Nhập tên người báo cáo (hoặc chọn nhân sự nội bộ).',
        ];
    }
}
