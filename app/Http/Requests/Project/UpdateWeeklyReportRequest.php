<?php

namespace App\Http\Requests\Project;

use App\Models\WeeklyReport;
use App\Support\Enums\WeeklyReportSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WeeklyReport $report */
        $report = $this->route('weeklyReport');

        return $this->user()?->can('update', $report) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editable = array_map(fn (WeeklyReportSection $s) => $s->value, WeeklyReportSection::editable());

        return [
            'executive_summary' => ['nullable', 'string', 'max:5000'],
            'sections' => ['sometimes', 'array', 'max:6'],
            'sections.*.section' => ['required', Rule::in($editable)],
            'sections.*.content' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.*.section.in' => 'Chỉ được sửa các thẻ Kết quả, Tình hình và Kế hoạch.',
            'executive_summary.max' => 'Tóm tắt điều hành quá dài (tối đa 5000 ký tự).',
            'sections.*.content.max' => 'Nội dung thẻ quá dài (tối đa 8000 ký tự).',
        ];
    }
}
