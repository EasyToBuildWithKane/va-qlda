<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportTaskRequest extends FormRequest
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
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        $projectId = $project->id;
        $maxRows = (int) config('business.project.max_import_rows', 200);

        return [
            'rows' => ['required', 'array', 'min:1', "max:{$maxRows}"],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.description' => ['nullable', 'string', 'max:10000'],
            'rows.*.sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'rows.*.status' => ['nullable', Rule::in(TaskStatus::values())],
            'rows.*.priority' => ['nullable', Rule::in(TaskPriority::values())],
            'rows.*.phase' => ['nullable', Rule::in(TaskPhase::values())],
            'rows.*.assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.reviewer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.start_date' => ['nullable', 'date'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.estimate_hours' => ['nullable', 'numeric', 'min:0'],
            'rows.*.progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxRows = (int) config('business.project.max_import_rows', 200);

        return [
            'rows.required' => 'Không có dòng hợp lệ để nhập.',
            'rows.min' => 'Cần ít nhất một công việc.',
            'rows.max' => "Mỗi lần nhập tối đa {$maxRows} dòng.",
            'rows.*.title.required' => 'Tiêu đề không được để trống.',
            'rows.*.title.max' => 'Tiêu đề tối đa 255 ký tự.',
        ];
    }
}
