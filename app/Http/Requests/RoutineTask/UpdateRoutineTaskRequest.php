<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineTaskRequest extends FormRequest
{
    use RoutineTaskFormRules;

    public function authorize(): bool
    {
        /** @var RoutineTask $task */
        $task = $this->route('routineTask');

        return $this->user()->can('update', $task);
    }

    protected function prepareForValidation(): void
    {
        $this->emptyToNull();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->fieldRules(titleSometimes: true), [
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->fieldMessages();
    }
}
