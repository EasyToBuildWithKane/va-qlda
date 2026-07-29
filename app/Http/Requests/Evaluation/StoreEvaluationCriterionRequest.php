<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EvaluationConfig $config */
        $config = $this->route('evaluationConfig');

        return $this->user()?->can('update', $config) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EvaluationConfig $config */
        $config = $this->route('evaluationConfig');

        return [
            'criteria_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('evaluation_criteria', 'criteria_code')->where('config_id', $config->id),
            ],
            'criteria_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'point_value' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'max_points' => ['nullable', 'integer', 'min:1', 'max:500'],
            'max_frequency' => ['nullable', 'integer', 'min:1', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'required_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'importance' => ['nullable', 'string', 'max:50'],
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
            'criteria_code.required' => 'Vui lòng nhập mã tiêu chí.',
            'criteria_code.unique' => 'Mã tiêu chí đã tồn tại trong cấu hình này.',
            'criteria_name.required' => 'Vui lòng nhập tên tiêu chí.',
            'category.required' => 'Vui lòng nhập danh mục tiêu chí.',
        ];
    }
}
