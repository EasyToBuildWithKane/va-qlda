<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationTemplate::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('template_code') === '' || $this->input('template_code') === null) {
            $this->merge(['template_code' => null]);
        }

        if (is_string($this->input('template_code'))) {
            $this->merge(['template_code' => strtoupper(trim($this->input('template_code')))]);
        }

        if ($this->input('position_code') === '') {
            $this->merge(['position_code' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('evaluation_templates', 'template_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position_code' => ['nullable', 'string', 'max:150'],
            'position_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'criteria' => ['nullable', 'array', 'max:100'],
            'criteria.*.criterion_id' => ['required', 'integer', 'exists:evaluation_criteria,id'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'criteria.*.required_score_label' => ['nullable', 'string', 'max:255'],
            'criteria.*.include_in_total' => ['sometimes', 'boolean'],
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên mẫu đánh giá.',
            'template_code.unique' => 'Mã mẫu đánh giá đã tồn tại.',
            'template_code.regex' => 'Mã mẫu chỉ gồm chữ cái và số (vd. MDG001).',
            'criteria.*.criterion_id.required' => 'Vui lòng chọn tiêu chí.',
            'criteria.*.criterion_id.exists' => 'Tiêu chí không tồn tại.',
        ];
    }
}
