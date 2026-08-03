<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class BulkRejectDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->allows('daily_report.review') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'exists:daily_reports,id'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn ít nhất một báo cáo.',
            'ids.max' => 'Chỉ được trả lại tối đa 50 báo cáo mỗi lần.',
            'notes.required' => 'Vui lòng nhập lý do trả lại.',
            'notes.min' => 'Lý do trả lại cần ít nhất 3 ký tự.',
        ];
    }
}
