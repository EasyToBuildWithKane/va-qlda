<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordEvaluationTemplateExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EvaluationTemplate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['filtered', 'all'])],
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
            'row_count' => ['required', 'integer', 'min:0'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:100'],
            'filters' => ['nullable', 'array'],
            'filename' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Thiếu phạm vi xuất.',
            'format.required' => 'Thiếu định dạng xuất.',
            'row_count.required' => 'Thiếu số dòng xuất.',
        ];
    }
}
