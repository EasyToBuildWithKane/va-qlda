<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('report'));
    }

    /**
     * Partial updates allowed (autosave). Fields validated only when present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'projects' => ['sometimes', 'nullable', 'array', 'max:20'],
            'projects.*.id' => ['required', 'integer'],
            'projects.*.name' => ['required', 'string', 'max:120'],
            'projects.*.tasks' => ['nullable', 'array', 'max:50'],
            'projects.*.tasks.*.id' => ['required', 'integer'],
            'projects.*.tasks.*.title' => ['required', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'goals_today' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'progress_update' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'blockers' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'improvement_suggestions' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'plan_tomorrow' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
