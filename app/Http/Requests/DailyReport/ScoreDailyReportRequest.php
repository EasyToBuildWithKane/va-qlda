<?php

namespace App\Http\Requests\DailyReport;

use Illuminate\Foundation\Http\FormRequest;

class ScoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('score', $this->route('report'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dimension = ['required', 'numeric', 'between:0,10'];

        return [
            'task_completion' => $dimension,
            'skill_score' => $dimension,
            'attitude_score' => $dimension,
            'kaizen_score' => $dimension,
            'expertise_score' => $dimension,
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
