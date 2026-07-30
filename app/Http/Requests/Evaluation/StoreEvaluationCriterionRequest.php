<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationCriterionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationCriterion::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('criteria_code') === '' || $this->input('criteria_code') === null) {
            $this->merge(['criteria_code' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(EvaluationCriterionScope::values())],
            'department_code' => ['nullable', 'string', 'max:100'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'local_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'criteria_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('evaluation_criteria', 'criteria_code'),
            ],
            'criteria_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'allow_half_score' => ['sometimes', 'boolean'],
            'score_1' => ['required', 'string', 'max:255'],
            'score_2' => ['required', 'string', 'max:255'],
            'score_3' => ['required', 'string', 'max:255'],
            'score_4' => ['required', 'string', 'max:255'],
            'score_5' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Vui lòng chọn phạm vi tiêu chí.',
            'scope.in' => 'Phạm vi tiêu chí không hợp lệ.',
            'criteria_code.unique' => 'Mã tiêu chí đã tồn tại.',
            'criteria_name.required' => 'Vui lòng nhập tên tiêu chí.',
            'category.required' => 'Vui lòng nhập loại tiêu chí.',
            'score_1.required' => 'Vui lòng nhập nhãn điểm 1.',
            'score_2.required' => 'Vui lòng nhập nhãn điểm 2.',
            'score_3.required' => 'Vui lòng nhập nhãn điểm 3.',
            'score_4.required' => 'Vui lòng nhập nhãn điểm 4.',
            'score_5.required' => 'Vui lòng nhập nhãn điểm 5.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('scope') === EvaluationCriterionScope::Department->value) {
                if (! filled($this->input('department_code'))) {
                    $v->errors()->add('department_code', 'Vui lòng chọn phòng ban.');
                }
            }
        });
    }
}
