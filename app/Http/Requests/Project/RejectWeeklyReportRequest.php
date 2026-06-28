<?php

namespace App\Http\Requests\Project;

use App\Models\WeeklyReport;
use Illuminate\Foundation\Http\FormRequest;

class RejectWeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WeeklyReport $report */
        $report = $this->route('weeklyReport');

        return $this->user()?->can('approve', $report) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do trả lại báo cáo.',
            'reason.min' => 'Lý do quá ngắn.',
        ];
    }
}
