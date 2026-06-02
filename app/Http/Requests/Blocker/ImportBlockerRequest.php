<?php

namespace App\Http\Requests\Blocker;

use App\Models\Blocker;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Blocker::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.severity' => ['required', Rule::in(BlockerSeverity::values())],
            'rows.*.status' => ['nullable', Rule::in(BlockerStatus::values())],
            'rows.*.owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.root_cause' => ['nullable', 'string', 'max:10000'],
            'rows.*.resolution' => ['nullable', 'string', 'max:10000'],
            'rows.*.description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Không có dòng hợp lệ để nhập.',
            'rows.max' => 'Mỗi lần nhập tối đa 200 dòng.',
            'rows.*.title.required' => 'Tiêu đề không được để trống.',
            'rows.*.severity.required' => 'Mức độ không hợp lệ.',
        ];
    }
}
