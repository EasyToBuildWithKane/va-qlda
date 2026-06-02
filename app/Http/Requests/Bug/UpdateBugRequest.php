<?php

namespace App\Http\Requests\Bug;

use App\Support\Enums\BugSeverity;
use App\Support\Enums\BugStatus;
use App\Support\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bug'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->input('project_id', $this->route('bug')->project_id);

        return [
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:10000'],
            'expected' => ['nullable', 'string', 'max:5000'],
            'actual' => ['nullable', 'string', 'max:5000'],
            'environment' => ['nullable', 'string', 'max:255'],
            'severity' => ['sometimes', 'required', Rule::in(BugSeverity::values())],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
            'status' => ['sometimes', 'required', Rule::in(BugStatus::values())],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
