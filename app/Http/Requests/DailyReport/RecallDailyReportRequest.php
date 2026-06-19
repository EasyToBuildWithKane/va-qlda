<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class RecallDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recall', $this->route('report'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'Lý do thu hồi không được vượt quá 500 ký tự.',
        ];
    }
}
