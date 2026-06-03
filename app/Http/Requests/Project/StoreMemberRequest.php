<?php

namespace App\Http\Requests\Project;

use App\Support\Enums\RateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
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
        /** @var \App\Models\Project $project */
        $project = $this->route('project');
        $projectId = $project->id;

        return [
            'employee_id' => [
                'required', 'integer', 'exists:employees,id',
                Rule::unique('project_member', 'employee_id')->where('project_id', $projectId),
            ],
            'role' => ['required', 'string', 'max:40'],
            'rate_type' => ['required', Rule::in(RateType::values())],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'allocation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'joined_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique' => 'Thành viên này đã ở trong dự án.',
        ];
    }
}
