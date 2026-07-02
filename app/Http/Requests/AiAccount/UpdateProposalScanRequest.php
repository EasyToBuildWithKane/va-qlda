<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalScanRequest extends FormRequest
{
    public const FIELD_KEYS = [
        'proposal_code',
        'proposal_date',
        'proposer_name',
        'proposer_position',
        'proposer_department',
        'send_to',
        'subject_about',
        'proposal_content',
        'justification',
        'cost_amount',
        'cost_unit',
        'quantity',
        'notes',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scan'));
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*' => ['array'],
            'fields.*.value' => ['nullable', 'string', 'max:8000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'Không có dữ liệu trường nào để cập nhật.',
        ];
    }

    /**
     * Chỉ giữ các key trường hợp lệ, giá trị đã trim.
     *
     * @return array<string, string>
     */
    public function cleanFieldValues(): array
    {
        $values = [];
        foreach ((array) $this->validated('fields') as $key => $field) {
            if (! in_array($key, self::FIELD_KEYS, true)) {
                continue;
            }
            $value = trim((string) ($field['value'] ?? ''));
            if ($value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (array_keys((array) $this->input('fields', [])) as $key) {
                if (! in_array($key, self::FIELD_KEYS, true)) {
                    $validator->errors()->add('fields', "Trường không hợp lệ: {$key}");
                }
            }
        });
    }
}
