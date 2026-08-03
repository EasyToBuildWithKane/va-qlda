<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use App\Support\Enums\EvaluationFormOrder;
use App\Support\Enums\EvaluationFormPeriodKind;
use App\Support\Enums\EvaluationFormRaterRole;
use App\Support\Enums\EvaluationFormStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationForm::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('form_code') === '' || $this->input('form_code') === null) {
            $this->merge(['form_code' => null]);
        }

        if (is_string($this->input('form_code'))) {
            $this->merge(['form_code' => strtoupper(trim($this->input('form_code')))]);
        }

        if ($this->input('template_id') === '' || $this->input('template_id') === null) {
            $this->merge(['template_id' => null]);
        }

        if ($this->input('period_start') === '') {
            $this->merge(['period_start' => null]);
        }

        if ($this->input('period_end') === '') {
            $this->merge(['period_end' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'form_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('evaluation_forms', 'form_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['nullable', 'integer', 'exists:evaluation_templates,id'],
            'type_id' => ['required', 'integer', 'exists:evaluation_form_types,id'],
            'period_kind' => ['required', Rule::in(EvaluationFormPeriodKind::values())],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'auto_create_next' => ['sometimes', 'boolean'],
            'manager_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'deadline' => ['required', 'date'],
            'evaluation_order' => ['required', Rule::in(EvaluationFormOrder::values())],
            'use_weight' => ['required', 'boolean'],
            'status' => ['sometimes', Rule::in(EvaluationFormStatus::values())],

            'watcher_ids' => ['nullable', 'array', 'max:50'],
            'watcher_ids.*' => ['integer', 'exists:employees,id'],

            'raters' => ['required', 'array', 'min:1', 'max:20'],
            'raters.*.role_key' => ['required', 'string', 'max:64', Rule::in(EvaluationFormRaterRole::values())],
            'raters.*.label' => ['required', 'string', 'max:255'],
            'raters.*.weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'raters.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*.field_key' => ['required', 'string', 'max:100'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['nullable', 'string', 'max:32'],
            'fields.*.is_enabled' => ['sometimes', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'criteria' => ['nullable', 'array', 'max:100'],
            'criteria.*.criterion_id' => ['nullable', 'integer', 'exists:evaluation_criteria,id'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'criteria.*.required_score_label' => ['nullable', 'string', 'max:255'],
            'criteria.*.evaluator_mode' => ['nullable', Rule::in(['all', 'tags'])],
            'criteria.*.evaluator_role_keys' => ['nullable', 'array'],
            'criteria.*.evaluator_role_keys.*' => ['string', 'max:64'],
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'assignees' => ['nullable', 'array', 'max:500'],
            'assignees.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'assignees.*.employee_code' => ['nullable', 'string', 'max:100'],
            'assignees.*.employee_name' => ['nullable', 'string', 'max:255'],
            'assignees.*.department_code' => ['nullable', 'string', 'max:100'],
            'assignees.*.department_name' => ['nullable', 'string', 'max:255'],
            'assignees.*.dept_head_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'assignees.*.direct_manager_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'assignees.*.board_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'assignees.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $kind = (string) $this->input('period_kind');

            if (in_array($kind, [
                EvaluationFormPeriodKind::Month->value,
                EvaluationFormPeriodKind::Quarter->value,
                EvaluationFormPeriodKind::HalfYear->value,
            ], true)) {
                if (! $this->filled('period_month')) {
                    $validator->errors()->add('period_month', 'Vui lòng chọn tháng / kỳ.');
                }
                if (! $this->filled('period_year')) {
                    $validator->errors()->add('period_year', 'Vui lòng chọn năm.');
                }
            }

            if ($kind === EvaluationFormPeriodKind::Year->value && ! $this->filled('period_year')) {
                $validator->errors()->add('period_year', 'Vui lòng chọn năm.');
            }

            if (in_array($kind, [
                EvaluationFormPeriodKind::Random->value,
                EvaluationFormPeriodKind::DateRange->value,
            ], true)) {
                if (! $this->filled('period_start') || ! $this->filled('period_end')) {
                    $validator->errors()->add('period_start', 'Vui lòng chọn khoảng ngày kỳ đánh giá.');
                }
            }

            if ($this->boolean('use_weight')) {
                $raters = $this->input('raters', []);
                $sum = 0.0;
                foreach ($raters as $rater) {
                    $sum += (float) ($rater['weight_percent'] ?? 0);
                }
                if (abs($sum - 100.0) > 0.01) {
                    $validator->errors()->add('raters', 'Tổng tỷ trọng hội đồng phải bằng 100%.');
                }
            }

            $assignees = $this->input('assignees', []);
            $employeeIds = array_map(static fn ($row) => (int) ($row['employee_id'] ?? 0), is_array($assignees) ? $assignees : []);
            if (count($employeeIds) !== count(array_unique($employeeIds))) {
                $validator->errors()->add('assignees', 'Danh sách nhân sự không được trùng người.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên phiếu đánh giá.',
            'type_id.required' => 'Vui lòng chọn loại đánh giá.',
            'type_id.exists' => 'Loại đánh giá không hợp lệ.',
            'period_kind.required' => 'Vui lòng chọn kỳ đánh giá.',
            'manager_employee_id.required' => 'Vui lòng chọn người quản lý.',
            'deadline.required' => 'Vui lòng chọn hạn đánh giá.',
            'evaluation_order.required' => 'Vui lòng chọn thứ tự đánh giá.',
            'use_weight.required' => 'Vui lòng chọn có dùng tỷ trọng hay không.',
            'raters.required' => 'Vui lòng cấu hình hội đồng đánh giá.',
            'raters.min' => 'Cần ít nhất một đối tượng đánh giá.',
            'criteria.*.name.required' => 'Vui lòng nhập tên tiêu chí.',
            'assignees.*.employee_id.required' => 'Vui lòng chọn nhân sự.',
            'assignees.*.dept_head_employee_id.required' => 'Vui lòng chọn trưởng phòng.',
            'assignees.*.direct_manager_employee_id.required' => 'Vui lòng chọn quản lý trực tiếp.',
        ];
    }
}
