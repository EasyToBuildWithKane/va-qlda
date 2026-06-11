<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderSprintsRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('sprints', 'id')->where('project_id', $project->id),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Project $project */
            $project = $this->route('project');
            $ids = $this->input('ids', []);
            $expected = $project->sprints()->count();
            if (count($ids) !== $expected) {
                $validator->errors()->add('ids', 'Danh sách sprint phải gồm đủ tất cả chu kỳ của dự án.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Thiếu danh sách thứ tự sprint.',
            'ids.*.exists' => 'Sprint không thuộc dự án này.',
            'ids.*.distinct' => 'Không được trùng sprint trong danh sách.',
        ];
    }
}
