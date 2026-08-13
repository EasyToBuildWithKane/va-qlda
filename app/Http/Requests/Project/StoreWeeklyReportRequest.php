<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Models\WeeklyReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWeeklyReportRequest extends FormRequest
{
    public const MAX_PERIOD_DAYS = 31;

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
            'week_start' => ['required_without:week_number', 'nullable', 'date'],
            'week_end' => ['required_without:week_number', 'nullable', 'date', 'after_or_equal:week_start'],
            'week_number' => ['required_without:week_start', 'nullable', 'integer', 'min:1', 'max:52'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'week_start.required_without' => 'Vui lòng chọn ngày bắt đầu.',
            'week_start.date' => 'Ngày bắt đầu không hợp lệ.',
            'week_end.required_without' => 'Vui lòng chọn ngày kết thúc.',
            'week_end.date' => 'Ngày kết thúc không hợp lệ.',
            'week_end.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'week_number.required_without' => 'Vui lòng chọn khoảng ngày báo cáo.',
            'week_number.integer' => 'Số kỳ không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->date('week_start');
            $end = $this->date('week_end');
            if (! $start || ! $end) {
                return;
            }

            if ($end->gt($start->copy()->addDays(self::MAX_PERIOD_DAYS - 1))) {
                $validator->errors()->add(
                    'week_end',
                    'Kỳ báo cáo tối đa '.self::MAX_PERIOD_DAYS.' ngày.',
                );
            }
        });
    }
}
