<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationConfig;
use App\Support\Enums\EvaluationTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEvaluationConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationConfig::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_code' => ['required', 'string', 'max:100'],
            'department_name' => ['required', 'string', 'max:255'],
            'local_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'template_type' => ['required', Rule::in(EvaluationTemplateType::values())],
            'config_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'base_score' => ['nullable', 'integer', 'min:0', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
            'criteria' => ['sometimes', 'array', 'max:200'],
            'criteria.*.criteria_code' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.criteria_name' => ['required_with:criteria', 'string', 'max:255'],
            'criteria.*.category' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.point_value' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'criteria.*.max_points' => ['nullable', 'integer', 'min:1', 'max:500'],
            'criteria.*.max_frequency' => ['nullable', 'integer', 'min:1', 'max:100'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'criteria.*.required_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'criteria.*.importance' => ['nullable', 'string', 'max:50'],
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'criteria.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_code.required' => 'Vui lòng chọn phòng ban.',
            'department_name.required' => 'Thiếu tên phòng ban.',
            'template_type.required' => 'Vui lòng chọn loại mẫu.',
            'template_type.in' => 'Loại mẫu không hợp lệ.',
            'config_name.required' => 'Vui lòng nhập tên cấu hình.',
            'effective_from.required' => 'Vui lòng chọn ngày hiệu lực từ.',
            'effective_to.after_or_equal' => 'Ngày hiệu lực tới phải sau hoặc bằng ngày bắt đầu.',
            'base_score.min' => 'Điểm khởi đầu tối thiểu là 0.',
            'base_score.max' => 'Điểm khởi đầu tối đa là 200.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $code = (string) $this->input('department_code');
            $type = (string) $this->input('template_type');
            $from = $this->input('effective_from');

            if ($code === '' || $type === '' || $from === null) {
                return;
            }

            $exists = EvaluationConfig::query()
                ->where('department_code', $code)
                ->where('template_type', $type)
                ->whereDate('effective_from', $from)
                ->exists();

            if ($exists) {
                $v->errors()->add(
                    'effective_from',
                    'Đã có cấu hình cùng phòng ban, loại mẫu và ngày bắt đầu hiệu lực.'
                );
            }

            if ($type === EvaluationTemplateType::PointSystem->value
                && $this->input('base_score') === null) {
                // default applied in controller; no error
            }

            if ($type === EvaluationTemplateType::Scorecard->value
                && $this->filled('base_score')) {
                // ignore base_score for scorecard — controller nulls it
            }

            $codes = collect($this->input('criteria', []))->pluck('criteria_code')->filter();
            if ($codes->count() !== $codes->unique()->count()) {
                $v->errors()->add('criteria', 'Mã tiêu chí không được trùng trong cùng cấu hình.');
            }
        });
    }
}
