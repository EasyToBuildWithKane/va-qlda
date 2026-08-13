<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToggleRoutineTaskStatusRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(RoutineTask::allowedStatusValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái không hợp lệ (todo / in_progress / done).',
        ];
    }
}
