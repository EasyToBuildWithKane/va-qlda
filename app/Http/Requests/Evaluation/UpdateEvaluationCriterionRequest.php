<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EvaluationCriterion $criterion */
        $criterion = $this->route('evaluationCriterion');

        return $this->user()?->can('update', $criterion) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('criteria_code'))) {
            $this->merge(['criteria_code' => strtoupper(trim($this->input('criteria_code')))]);
        }

        $deptCode = trim((string) $this->input('department_code', ''));
        if ($deptCode === '') {
            $this->merge([
                'scope' => EvaluationCriterionScope::General->value,
                'department_code' => null,
                'department_name' => null,
                'local_department_id' => null,
            ]);
        } else {
            $this->merge([
                'scope' => EvaluationCriterionScope::Department->value,
                'department_code' => $deptCode,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EvaluationCriterion $criterion */
        $criterion = $this->route('evaluationCriterion');

        return [
            'scope' => ['required', Rule::in(EvaluationCriterionScope::values())],
            'department_code' => ['nullable', 'string', 'max:100'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'local_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'criteria_code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('evaluation_criteria', 'criteria_code')->ignore($criterion->id),
            ],
            'criteria_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'allow_half_score' => ['sometimes', 'boolean'],
            'score_levels' => [
                'required',
                'array',
                'min:'.EvaluationCriterion::MIN_SCORE_LEVELS,
                'max:'.EvaluationCriterion::MAX_SCORE_LEVELS,
            ],
            'score_levels.*.code' => ['nullable', 'string', 'max:50'],
            'score_levels.*.label' => ['required', 'string', 'max:255'],
            'score_levels.*.description' => ['nullable', 'string', 'max:500'],
            'score_levels.*.weight' => ['required', 'numeric', 'min:-999', 'max:999'],
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
            'scope.required' => 'Vui lòng chọn phạm vi tiêu chí.',
            'scope.in' => 'Phạm vi tiêu chí không hợp lệ.',
            'criteria_code.required' => 'Vui lòng nhập mã tiêu chí.',
            'criteria_code.unique' => 'Mã tiêu chí đã tồn tại.',
            'criteria_code.regex' => 'Mã tiêu chí chỉ gồm chữ cái và số (vd. TCVA001).',
            'criteria_name.required' => 'Vui lòng nhập tên tiêu chí.',
            'category.required' => 'Vui lòng nhập loại tiêu chí.',
            'score_levels.required' => 'Vui lòng cấu hình thang điểm đánh giá.',
            'score_levels.min' => 'Thang điểm cần ít nhất '.EvaluationCriterion::MIN_SCORE_LEVELS.' mức.',
            'score_levels.max' => 'Thang điểm tối đa '.EvaluationCriterion::MAX_SCORE_LEVELS.' mức.',
            'score_levels.*.label.required' => 'Vui lòng nhập nhãn cho mỗi mức điểm.',
            'score_levels.*.weight.required' => 'Vui lòng nhập trọng số cho mỗi mức điểm.',
            'score_levels.*.weight.numeric' => 'Trọng số phải là số.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $scope = $this->input('scope');

            if ($scope === EvaluationCriterionScope::General->value) {
                $canGeneral = app(WorkspaceScopeResolver::class)->canManageAll($this->user());
                if (! $canGeneral) {
                    $v->errors()->add(
                        'department_code',
                        'Chỉ siêu quản trị mới đặt tiêu chí chung. Vui lòng chọn phòng ban.'
                    );
                }
            }

            if ($scope === EvaluationCriterionScope::Department->value) {
                if (! filled($this->input('department_code'))) {
                    $v->errors()->add('department_code', 'Vui lòng chọn phòng ban.');
                }
            }

            if (! $this->boolean('allow_half_score')) {
                foreach ((array) $this->input('score_levels', []) as $index => $level) {
                    $weight = $level['weight'] ?? null;
                    if (is_numeric($weight) && fmod((float) $weight, 1.0) !== 0.0) {
                        $v->errors()->add(
                            "score_levels.{$index}.weight",
                            'Trọng số phải là số nguyên khi chưa bật chấm 0.5.'
                        );
                    }
                }
            }
        });
    }
}
