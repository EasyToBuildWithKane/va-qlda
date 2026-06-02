<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\TaskPriority;
use App\Support\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(TaskStatus::values())],
            'priority' => ['nullable', Rule::in(TaskPriority::values())],
            'estimate_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
