<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class BulkScoreDailyReportRequest extends FormRequest
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
            'task_completion' => ['required', 'numeric', 'min:0', 'max:10'],
            'skill_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'attitude_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'kaizen_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'expertise_score' => ['required', 'numeric', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn ít nhất một báo cáo.',
            'ids.max' => 'Chỉ được duyệt tối đa 50 báo cáo mỗi lần.',
            'task_completion.required' => 'Vui lòng chấm điểm Hoàn thành.',
            'skill_score.required' => 'Vui lòng chấm điểm Kỹ năng.',
            'attitude_score.required' => 'Vui lòng chấm điểm Thái độ.',
            'kaizen_score.required' => 'Vui lòng chấm điểm Kaizen.',
            'expertise_score.required' => 'Vui lòng chấm điểm Chuyên môn.',
        ];
    }
}
