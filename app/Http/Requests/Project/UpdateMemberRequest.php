<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\RateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:40'],
            'rate_type' => ['required', Rule::in(RateType::values())],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'allocation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'joined_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }
}
