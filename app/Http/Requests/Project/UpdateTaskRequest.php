<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Managers/reviewers manage any task; members may edit project tasks.
        return $this->user()->can('contribute', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $projectId = $this->route('project')->id;
        $taskId = $this->route('task')->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'status' => ['sometimes', 'required', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*' => ['integer', 'different:'.$taskId, Rule::exists('tasks', 'id')->where('project_id', $projectId)],
        ];
    }
}
