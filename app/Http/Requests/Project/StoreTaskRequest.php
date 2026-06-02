<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\TaskPhase;
use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'parent_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'epic_id' => ['nullable', 'integer', Rule::exists('epics', 'id')->where('project_id', $projectId)],
            'story_points' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(TaskStatus::values())],
            'priority' => ['required', Rule::in(TaskPriority::values())],
            'phase' => ['nullable', Rule::in(TaskPhase::values())],
            'is_milestone' => ['nullable', 'boolean'],
            'assignee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:employees,id'],
            'reviewer_id' => ['nullable', 'integer', 'exists:employees,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimate_hours' => ['nullable', 'numeric', 'min:0'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*' => ['integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
        ];
    }
}
