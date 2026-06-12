<?php

namespace App\Http\Requests\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Support\DailyReportCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DailyReport::class);
    }

    /**
     * Default to today so the one-per-day unique rule always runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'date' => $this->input('date') ?: DailyReportCalendar::today(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employeeId = $this->user()->employee_id;

        return [
            'date' => [
                'required', 'date',
                // one report per employee per day
                Rule::unique('daily_reports', 'date')
                    ->where(fn ($q) => $q->where('employee_id', $employeeId)),
            ],
            'project_id' => ['nullable', 'integer'],
            'projects' => ['nullable', 'array', 'max:20'],
            'projects.*.id' => ['required', 'integer'],
            'projects.*.name' => ['required', 'string', 'max:120'],
            'projects.*.tasks' => ['nullable', 'array', 'max:50'],
            'projects.*.tasks.*.id' => ['nullable', 'integer', 'min:0'],
            'projects.*.tasks.*.status' => ['nullable', 'string', 'max:32'],
            'projects.*.tasks.*._localKey' => ['nullable', 'string', 'max:64'],
            'projects.*.tasks.*.title' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'goals_today' => ['nullable', 'string', 'max:20000'],
            'progress_update' => ['nullable', 'string', 'max:20000'],
            'blockers' => ['nullable', 'string', 'max:20000'],
            'improvement_suggestions' => ['nullable', 'string', 'max:20000'],
            'plan_tomorrow' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'Bạn đã có báo cáo cho ngày này.',
        ];
    }
}
