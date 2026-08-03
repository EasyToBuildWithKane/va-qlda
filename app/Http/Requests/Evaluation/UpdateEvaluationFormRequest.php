<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use Illuminate\Validation\Rule;

class UpdateEvaluationFormRequest extends StoreEvaluationFormRequest
{
    public function authorize(): bool
    {
        /** @var EvaluationForm|null $form */
        $form = $this->route('evaluationForm');

        return $form instanceof EvaluationForm
            && ($this->user()?->can('update', $form) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var EvaluationForm|null $form */
        $form = $this->route('evaluationForm');
        $formId = $form instanceof EvaluationForm ? $form->id : null;

        $rules['form_code'] = [
            'nullable',
            'string',
            'max:100',
            'regex:/^[A-Z0-9]+$/',
            Rule::unique('evaluation_forms', 'form_code')->ignore($formId),
        ];

        return $rules;
    }
}
