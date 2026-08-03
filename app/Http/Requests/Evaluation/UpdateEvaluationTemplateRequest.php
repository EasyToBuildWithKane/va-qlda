<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use App\Support\Enums\EvaluationTemplateFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EvaluationTemplate|null $template */
        $template = $this->route('evaluationTemplate');

        return $template instanceof EvaluationTemplate
            && ($this->user()?->can('update', $template) ?? false);
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
        /** @var EvaluationTemplate $template */
        $template = $this->route('evaluationTemplate');

        return [
            'template_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('evaluation_templates', 'template_code')->ignore($template->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position_code' => ['nullable', 'string', 'max:150'],
            'position_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'titles' => ['nullable', 'array', 'max:50'],
            'titles.*.code' => ['required', 'string', 'max:150'],
            'titles.*.name' => ['required', 'string', 'max:255'],
            'titles.*.hrm_uuid' => ['nullable', 'string', 'max:64'],
            'titles.*.source' => ['nullable', 'string', 'max:32'],

            'ranks' => ['nullable', 'array', 'max:50'],
            'ranks.*.code' => ['required', 'string', 'max:150'],
            'ranks.*.name' => ['required', 'string', 'max:255'],
            'ranks.*.hrm_uuid' => ['nullable', 'string', 'max:64'],
            'ranks.*.source' => ['nullable', 'string', 'max:32'],

            'criteria' => ['nullable', 'array', 'max:100'],
            'criteria.*.source' => ['nullable', Rule::in(['catalog', 'custom'])],
            'criteria.*.criterion_id' => ['nullable', 'integer', 'exists:evaluation_criteria,id'],
            'criteria.*.custom_name' => ['nullable', 'string', 'max:255'],
            'criteria.*.custom_code' => ['nullable', 'string', 'max:100'],
            'criteria.*.custom_category' => ['nullable', 'string', 'max:100'],
            'criteria.*.custom_description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'criteria.*.required_score_label' => ['nullable', 'string', 'max:255'],
            'criteria.*.include_in_total' => ['sometimes', 'boolean'],
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'fields' => ['nullable', 'array', 'max:50'],
            'fields.*.field_key' => ['nullable', 'string', 'max:100'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', Rule::in(EvaluationTemplateFieldType::values())],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['nullable', 'string', 'max:255'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
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
            'criteria.*.criterion_id.exists' => 'Tiêu chí không tồn tại.',
            'fields.*.label.required' => 'Vui lòng nhập nhãn trường tùy biến.',
            'fields.*.field_type.required' => 'Vui lòng chọn kiểu trường.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $titles = $this->input('titles', []);
            $ranks = $this->input('ranks', []);
            if (is_array($titles) && is_array($ranks) && count($titles) > 0 && count($ranks) > 0) {
                $validator->errors()->add(
                    'titles',
                    'Chỉ chọn chức danh hoặc cấp bậc — không chọn cả hai.'
                );
            }

            foreach ($this->input('criteria', []) as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $source = $row['source'] ?? (! empty($row['criterion_id']) ? 'catalog' : 'custom');
                if ($source === 'catalog' && empty($row['criterion_id'])) {
                    $validator->errors()->add("criteria.{$i}.criterion_id", 'Vui lòng chọn tiêu chí từ danh mục.');
                }
                if ($source === 'custom' && trim((string) ($row['custom_name'] ?? $row['name'] ?? '')) === '') {
                    $validator->errors()->add("criteria.{$i}.custom_name", 'Vui lòng nhập tên tiêu chí tùy chỉnh.');
                }
            }
        });
    }
}
