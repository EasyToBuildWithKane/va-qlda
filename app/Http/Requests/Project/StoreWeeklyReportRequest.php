<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Models\WeeklyReport;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()?->can('generate', [WeeklyReport::class, $project]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'week_number' => ['required', 'integer', 'min:1', 'max:52'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'week_number.required' => 'Vui lòng chọn tuần báo cáo.',
            'week_number.integer' => 'Số tuần không hợp lệ.',
        ];
    }
}
