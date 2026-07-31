<?php

namespace App\Http\Requests\Evaluation;

use App\Models\Evaluation\EvaluationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportEvaluationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EvaluationTemplate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.template_code' => ['nullable', 'string', 'max:100'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.description' => ['nullable', 'string', 'max:5000'],
            'rows.*.position_code' => ['nullable', 'string', 'max:150'],
            'rows.*.position_name' => ['nullable', 'string', 'max:255'],
            'rows.*.is_active' => ['sometimes', 'boolean'],
            'rows.*.criteria' => ['nullable', 'array', 'max:100'],
            'rows.*.criteria.*.criterion_id' => ['required', 'integer', 'exists:evaluation_criteria,id'],
            'rows.*.criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'rows.*.criteria.*.required_score_label' => ['nullable', 'string', 'max:255'],
            'rows.*.criteria.*.include_in_total' => ['sometimes', 'boolean'],
            'rows.*.criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng dữ liệu để nhập.',
            'rows.max' => 'Mỗi lần nhập tối đa 200 mẫu đánh giá.',
            'rows.*.name.required' => 'Vui lòng nhập tên mẫu đánh giá.',
            'rows.*.criteria.*.criterion_id.exists' => 'Tiêu chí không tồn tại.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('rows', []);
            if (! is_array($rows)) {
                return;
            }

            $seen = [];
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['template_code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                if (isset($seen[$code])) {
                    $validator->errors()->add(
                        "rows.{$index}.template_code",
                        "Mã mẫu «{$code}» bị trùng trong cùng lô nhập."
                    );
                }
                $seen[$code] = true;

                if (EvaluationTemplate::query()->where('template_code', $code)->exists()) {
                    $validator->errors()->add(
                        "rows.{$index}.template_code",
                        "Mã mẫu «{$code}» đã tồn tại."
                    );
                }

                $criteria = $row['criteria'] ?? [];
                if (! is_array($criteria)) {
                    continue;
                }
                $critSeen = [];
                foreach ($criteria as $ci => $line) {
                    $cid = (int) ($line['criterion_id'] ?? 0);
                    if ($cid < 1) {
                        continue;
                    }
                    if (isset($critSeen[$cid])) {
                        $validator->errors()->add(
                            "rows.{$index}.criteria.{$ci}.criterion_id",
                            'Tiêu chí bị trùng trong cùng mẫu.'
                        );
                    }
                    $critSeen[$cid] = true;
                }
            }
        });
    }
}
