<?php

namespace App\Http\Requests\RoutineTask;

use App\Domain\RoutineTask\Models\RoutineTask;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineTaskRequest extends FormRequest
{
    use RoutineTaskFormRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', RoutineTask::class);
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
        return $this->fieldRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->fieldMessages();
    }
}
