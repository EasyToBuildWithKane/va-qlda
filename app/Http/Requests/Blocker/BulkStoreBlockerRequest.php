<?php

namespace App\Http\Requests\Blocker;

use App\Models\Blocker;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreBlockerRequest extends FormRequest
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
            'defaults' => ['nullable', 'array'],
            'defaults.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'defaults.severity' => ['nullable', Rule::in(BlockerSeverity::values())],
            'defaults.status' => ['nullable', Rule::in(BlockerStatus::values())],
            'defaults.owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'defaults.due_date' => ['nullable', 'date'],
            'rows' => ['required', 'array', 'min:1', 'max:50'],
            'rows.*.title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Chưa có test case nào để ghi nhận.',
            'rows.min' => 'Cần ít nhất một test case.',
            'rows.max' => 'Mỗi lần ghi nhận tối đa 50 test case.',
            'rows.*.title.required' => 'Tiêu đề không được để trống.',
            'rows.*.title.max' => 'Tiêu đề tối đa 255 ký tự.',
        ];
    }
}
