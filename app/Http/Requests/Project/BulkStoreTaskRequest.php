<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->route('project')->id;

        return [
            'defaults' => ['nullable', 'array'],
            'defaults.sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'defaults.status' => ['nullable', Rule::in(TaskStatus::values())],
            'defaults.priority' => ['nullable', Rule::in(TaskPriority::values())],
            'defaults.phase' => ['nullable', Rule::in(TaskPhase::values())],
            'defaults.assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'defaults.assignee_ids' => ['nullable', 'array'],
            'defaults.assignee_ids.*' => ['integer', 'exists:employees,id'],
            'defaults.reviewer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'defaults.reporter_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Chưa có công việc nào để tạo.',
            'rows.min' => 'Cần ít nhất một công việc.',
            'rows.max' => 'Mỗi lần tạo tối đa 50 công việc.',
            'rows.*.title.required' => 'Tiêu đề không được để trống.',
            'rows.*.title.max' => 'Tiêu đề tối đa 255 ký tự.',
        ];
    }
}
