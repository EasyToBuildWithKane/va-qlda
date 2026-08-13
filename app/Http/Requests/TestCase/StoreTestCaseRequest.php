<?php

namespace App\Http\Requests\TestCase;

use App\Http\Requests\TestCase\Concerns\CleansReferenceLinks;
use App\Models\TestCase;
use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestCaseRequest extends FormRequest
{
    use CleansReferenceLinks;

    public function authorize(): bool
    {
        return $this->user()->can('create', TestCase::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_links' => $this->cleanedReferenceLinks(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'suite_id' => ['nullable', 'integer', Rule::exists('test_suites', 'id')->where('project_id', $this->input('project_id'))],
            'suite_name' => ['nullable', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'blocker_id' => ['nullable', 'integer', 'exists:blockers,id'],
            'title' => ['required', 'string', 'max:255'],
            'preconditions' => ['nullable', 'string', 'max:10000'],
            'steps' => ['nullable', 'array', 'max:100'],
            'steps.*.step' => ['required', 'string', 'max:2000'],
            'steps.*.expected' => ['nullable', 'string', 'max:2000'],
            'expected_result' => ['nullable', 'string', 'max:10000'],
            ...$this->referenceLinkRules(),
            'priority' => ['required', Rule::in(TestCasePriority::values())],
            'status' => ['nullable', Rule::in(TestCaseStatus::values())],
            'owner_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Vui lòng chọn dự án.',
            'title.required' => 'Tiêu đề test case không được để trống.',
            'priority.required' => 'Mức độ ưu tiên không hợp lệ.',
            'steps.*.step.required' => 'Nội dung bước kiểm thử không được để trống.',
            ...$this->referenceLinkMessages(),
        ];
    }
}
