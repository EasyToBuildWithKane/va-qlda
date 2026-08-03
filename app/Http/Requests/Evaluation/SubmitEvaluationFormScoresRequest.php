<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use App\Support\Evaluation\EvaluationFormScoringService;
use Illuminate\Validation\Validator;

class SubmitEvaluationFormScoresRequest extends SaveEvaluationFormScoresRequest
{
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            /** @var EvaluationForm $form */
            $form = $this->route('evaluationForm');
            $role = (string) $this->input('rater_role_key', '');
            $service = app(EvaluationFormScoringService::class);
            $required = $service->criteriaForRole($form->loadMissing('criteria'), $role);
            $lineIds = collect($this->input('lines', []))
                ->filter(fn ($line) => filled($line['score_level_label'] ?? null) || filled($line['score_level_code'] ?? null))
                ->pluck('form_criterion_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($required as $criterion) {
                if (! in_array((int) $criterion->id, $lineIds, true)) {
                    $validator->errors()->add(
                        'lines',
                        'Vui lòng chấm đủ tất cả tiêu chí trước khi nộp («'.$criterion->name.'»).'
                    );
                    break;
                }
            }
        });
    }
}
