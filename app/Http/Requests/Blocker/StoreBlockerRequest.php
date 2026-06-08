<?php

namespace App\Http\Requests\Blocker;

use App\Models\Blocker;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Blocker::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->input('project_id'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date'],
            'severity' => ['required', Rule::in(BlockerSeverity::values())],
            'status' => ['nullable', Rule::in(BlockerStatus::values())],
            'owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'evidence_links' => ['nullable', 'array', 'max:20'],
            'evidence_links.*.label' => ['nullable', 'string', 'max:120'],
            'evidence_links.*.url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidence_links.*.url.url' => 'Link dẫn chứng phải là URL hợp lệ (https://…).',
            'evidence_links.*.url.required' => 'Mỗi dòng dẫn chứng cần có địa chỉ link.',
        ];
    }
}
