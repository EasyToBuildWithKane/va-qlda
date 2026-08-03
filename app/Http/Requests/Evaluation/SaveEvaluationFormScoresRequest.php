<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationForm;
use App\Models\Evaluation\EvaluationFormAssignee;
use App\Support\Evaluation\EvaluationFormScoringService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveEvaluationFormScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EvaluationForm $form */
        $form = $this->route('evaluationForm');
        /** @var EvaluationFormAssignee $assignee */
        $assignee = $this->route('assignee');
        $role = (string) $this->input('rater_role_key', '');

        return $this->user()?->can('score', [$form, $assignee, $role]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rater_role_key' => ['required', 'string', 'max:64'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'lines' => ['nullable', 'array', 'max:100'],
            'lines.*.form_criterion_id' => ['required', 'integer'],
            'lines.*.score_level_code' => ['nullable', 'string', 'max:64'],
            'lines.*.score_level_label' => ['nullable', 'string', 'max:255'],
            'lines.*.score_weight' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'field_values' => ['nullable', 'array', 'max:50'],
            'field_values.*.form_field_id' => ['required', 'integer'],
            'field_values.*.value' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var EvaluationForm $form */
            $form = $this->route('evaluationForm');
            /** @var EvaluationFormAssignee $assignee */
            $assignee = $this->route('assignee');
            $role = (string) $this->input('rater_role_key', '');

            if ($assignee->form_id !== $form->id) {
                $validator->errors()->add('assignee', 'Nhân sự không thuộc phiếu này.');
            }

            $service = app(EvaluationFormScoringService::class);
            if (! $service->canScoreRole($this->user(), $form, $assignee, $role)) {
                $validator->errors()->add('rater_role_key', 'Bạn không thể chấm với vai trò này.');
            }

            $allowedIds = $service->criteriaForRole($form->loadMissing('criteria'), $role)
                ->pluck('id')
                ->all();

            foreach ($this->input('lines', []) as $i => $line) {
                $cid = (int) ($line['form_criterion_id'] ?? 0);
                if ($cid && ! in_array($cid, $allowedIds, true)) {
                    $validator->errors()->add("lines.$i.form_criterion_id", 'Tiêu chí không thuộc phạm vi chấm của vai trò này.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rater_role_key.required' => 'Vui lòng chọn vai trò chấm điểm.',
            'lines.*.form_criterion_id.required' => 'Thiếu tiêu chí chấm điểm.',
        ];
    }
}
