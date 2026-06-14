<?php

namespace App\Http\Requests\Coaching;

use App\Models\CoachingCourse;
use App\Support\Enums\CoachingCourseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoachingCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CoachingCourse::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'coach_name' => ['nullable', 'string', 'max:255'],
            'student_id' => ['nullable', 'integer', 'exists:employees,id'],
            'coach_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['required', 'string', Rule::in(CoachingCourseStatus::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_fee' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'total_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên khóa học.',
        ];
    }
}
