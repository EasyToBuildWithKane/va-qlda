<?php

namespace App\Http\Requests\Coaching;

use App\Models\CoachingCourse;
use App\Support\Enums\CoachingSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoachingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CoachingCourse $course */
        $course = $this->route('course');

        return $this->user()->can('update', $course);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->input('date') === '') {
            $merge['date'] = null;
        }
        if ($this->input('total_hours') === '') {
            $merge['total_hours'] = null;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CoachingCourse $course */
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'session_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('coaching_sessions', 'session_number')
                    ->where(fn ($q) => $q->where('course_id', $course->id)),
            ],
            'date' => ['nullable', 'date'],
            'total_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'status' => ['nullable', 'string', Rule::in(CoachingSessionStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên buổi học.',
            'session_number.unique' => 'Số thứ tự buổi đã tồn tại trong khóa này.',
            'total_hours.max' => 'Tổng giờ một buổi không được vượt quá 99,99 giờ.',
        ];
    }
}
