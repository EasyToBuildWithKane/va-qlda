<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationConfig;
use Illuminate\Foundation\Http\FormRequest;

class ReorderEvaluationCriteriaRequest extends FormRequest
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
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordered_ids.required' => 'Thiếu danh sách thứ tự tiêu chí.',
        ];
    }
}
