<?php

namespace App\Http\Requests\TestCase;

use App\Models\TestCase;
use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TestCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.priority' => ['required', Rule::in(TestCasePriority::values())],
            'rows.*.status' => ['nullable', Rule::in(TestCaseStatus::values())],
            'rows.*.suite_id' => ['nullable', 'integer', Rule::exists('test_suites', 'id')->where('project_id', $this->input('project_id'))],
            'rows.*.owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.preconditions' => ['nullable', 'string', 'max:10000'],
            'rows.*.expected_result' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng hợp lệ để nhập.',
            'rows.max' => 'Mỗi lần nhập tối đa 200 test case.',
            'rows.*.title.required' => 'Tiêu đề test case không được để trống.',
            'rows.*.priority.required' => 'Mức độ ưu tiên không hợp lệ.',
        ];
    }
}
