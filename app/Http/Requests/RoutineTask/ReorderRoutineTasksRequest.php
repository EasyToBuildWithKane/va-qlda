<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRoutineTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reorder', RoutineTask::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Gửi danh sách thứ tự công việc.',
            'ids.*.uuid' => 'Mỗi phần tử phải là UUID hợp lệ.',
        ];
    }
}
