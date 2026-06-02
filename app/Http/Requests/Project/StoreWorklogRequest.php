<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contribute', $this->route('project'));
    }

    /**
     * Default the worker to the current user (members log their own time).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('employee_id')) {
            $this->merge(['employee_id' => $this->user()->employee_id]);
        }
        if (! $this->filled('date')) {
            $this->merge(['date' => now()->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
