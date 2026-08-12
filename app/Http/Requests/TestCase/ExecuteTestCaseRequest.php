<?php

namespace App\Http\Requests\TestCase;

use App\Support\Enums\TestCaseRunResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteTestCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\TestCase $testCase */
        $testCase = $this->route('testCase');

        return $this->user()->can('execute', $testCase);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(TestCaseRunResult::values())],
            'actual_result' => ['nullable', 'string', 'max:10000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'create_blocker' => ['nullable', 'boolean'],
            'blocker_title' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->boolean('create_blocker') && $this->input('result') === TestCaseRunResult::Fail->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result.required' => 'Kết quả thực thi không hợp lệ.',
            'blocker_title.required' => 'Vui lòng nhập tiêu đề vướng mắc khi tạo mới.',
        ];
    }
}
