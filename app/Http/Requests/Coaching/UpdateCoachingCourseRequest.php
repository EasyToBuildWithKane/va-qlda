<?php

namespace App\Http\Requests\Coaching;

use App\Support\Enums\CoachingCourseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoachingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:employees,id'],
            'coach_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['sometimes', 'string', Rule::in(CoachingCourseStatus::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'total_fee' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'total_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
