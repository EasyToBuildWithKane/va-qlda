<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationCriterion;
use App\Support\Enums\EvaluationCriterionScope;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportEvaluationCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationCriterion::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rows = (array) $this->input('rows', []);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (is_string($row['criteria_code'] ?? null)) {
                $rows[$index]['criteria_code'] = strtoupper(trim($row['criteria_code'])) ?: null;
            }

            $deptCode = trim((string) ($row['department_code'] ?? ''));
            if ($deptCode === '') {
                $rows[$index]['scope'] = EvaluationCriterionScope::General->value;
                $rows[$index]['department_code'] = null;
            } else {
                $rows[$index]['scope'] = EvaluationCriterionScope::Department->value;
                $rows[$index]['department_code'] = $deptCode;
            }
        }

        $this->merge(['rows' => $rows]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.scope' => ['required', Rule::in(EvaluationCriterionScope::values())],
            'rows.*.department_code' => ['nullable', 'string', 'max:100'],
            'rows.*.department_name' => ['nullable', 'string', 'max:255'],
            'rows.*.criteria_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Z][A-Z0-9]*$/',
                Rule::unique('evaluation_criteria', 'criteria_code'),
            ],
            'rows.*.criteria_name' => ['required', 'string', 'max:255'],
            'rows.*.category' => ['required', 'string', 'max:100'],
            'rows.*.description' => ['nullable', 'string', 'max:5000'],
            'rows.*.allow_half_score' => ['sometimes', 'boolean'],
            'rows.*.score_levels' => [
                'required',
                'array',
                'min:'.EvaluationCriterion::MIN_SCORE_LEVELS,
                'max:'.EvaluationCriterion::MAX_SCORE_LEVELS,
            ],
            'rows.*.score_levels.*.code' => ['nullable', 'string', 'max:50'],
            'rows.*.score_levels.*.label' => ['required', 'string', 'max:255'],
            'rows.*.score_levels.*.description' => ['nullable', 'string', 'max:500'],
            'rows.*.score_levels.*.weight' => ['required', 'numeric', 'min:-999', 'max:999'],
            'rows.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng dữ liệu để nhập.',
            'rows.min' => 'Cần ít nhất 1 dòng dữ liệu để nhập.',
            'rows.max' => 'Mỗi lần nhập tối đa 200 dòng.',
            'rows.*.scope.required' => 'Không xác định được phạm vi tiêu chí.',
            'rows.*.criteria_code.unique' => 'Mã tiêu chí đã tồn tại trong hệ thống.',
            'rows.*.criteria_code.regex' => 'Mã tiêu chí chỉ gồm chữ cái và số (vd. TCVA001).',
            'rows.*.criteria_name.required' => 'Vui lòng nhập tên tiêu chí.',
            'rows.*.category.required' => 'Vui lòng nhập loại tiêu chí.',
            'rows.*.score_levels.required' => 'Vui lòng cấu hình thang điểm đánh giá.',
            'rows.*.score_levels.min' => 'Thang điểm cần ít nhất '.EvaluationCriterion::MIN_SCORE_LEVELS.' mức.',
            'rows.*.score_levels.max' => 'Thang điểm tối đa '.EvaluationCriterion::MAX_SCORE_LEVELS.' mức.',
            'rows.*.score_levels.*.label.required' => 'Vui lòng nhập nhãn cho mỗi mức điểm.',
            'rows.*.score_levels.*.weight.required' => 'Vui lòng nhập trọng số cho mỗi mức điểm.',
            'rows.*.score_levels.*.weight.numeric' => 'Trọng số phải là số.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rows = (array) $this->input('rows', []);
            $scopeResolver = app(WorkspaceScopeResolver::class);
            $canGeneral = $scopeResolver->canManageAll($this->user());
            $seenCodes = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $scope = $row['scope'] ?? null;

                if ($scope === EvaluationCriterionScope::General->value && ! $canGeneral) {
                    $v->errors()->add(
                        "rows.{$index}.department_code",
                        'Chỉ siêu quản trị mới tạo được tiêu chí chung. Vui lòng chọn phòng ban.'
                    );
                }

                if ($scope === EvaluationCriterionScope::Department->value && ! filled($row['department_code'] ?? null)) {
                    $v->errors()->add("rows.{$index}.department_code", 'Vui lòng chọn phòng ban.');
                }

                if (! ($row['allow_half_score'] ?? false)) {
                    foreach ((array) ($row['score_levels'] ?? []) as $levelIndex => $level) {
                        $weight = $level['weight'] ?? null;
                        if (is_numeric($weight) && fmod((float) $weight, 1.0) !== 0.0) {
                            $v->errors()->add(
                                "rows.{$index}.score_levels.{$levelIndex}.weight",
                                'Trọng số phải là số nguyên khi chưa bật chấm 0.5.'
                            );
                        }
                    }
                }

                $code = strtoupper(trim((string) ($row['criteria_code'] ?? '')));
                if ($code !== '') {
                    if (isset($seenCodes[$code])) {
                        $v->errors()->add(
                            "rows.{$index}.criteria_code",
                            "Mã tiêu chí \"{$code}\" bị trùng với dòng ".($seenCodes[$code] + 1).' trong cùng file.'
                        );
                    } else {
                        $seenCodes[$code] = $index;
                    }
                }
            }
        });
    }
}
