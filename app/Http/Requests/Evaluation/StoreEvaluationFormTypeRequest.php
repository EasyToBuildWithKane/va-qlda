<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationFormTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationForm::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('evaluation_form_types', 'name'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên loại đánh giá.',
            'name.unique' => 'Loại đánh giá này đã tồn tại.',
        ];
    }
}
