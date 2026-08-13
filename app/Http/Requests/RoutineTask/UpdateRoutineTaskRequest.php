<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoutineTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RoutineTask $task */
        $task = $this->route('routineTask');

        return $this->user()->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(RoutineTask::allowedStatusValues())],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Nhập tiêu đề công việc thường xuyên.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ (todo / in_progress / done).',
        ];
    }
}
