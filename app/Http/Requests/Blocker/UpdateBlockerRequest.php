<?php

namespace App\Http\Requests\Blocker;

use App\Models\Blocker;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('blocker'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date'],
            'severity' => ['sometimes', 'required', Rule::in(BlockerSeverity::values())],
            'status' => ['sometimes', 'required', Rule::in(BlockerStatus::values())],
            'owner_id' => ['nullable', 'integer', 'exists:employees,id'],
            'resolution' => ['nullable', 'string', 'max:10000'],
            'evidence_links' => ['nullable', 'array', 'max:20'],
            'evidence_links.*.label' => ['nullable', 'string', 'max:120'],
            'evidence_links.*.url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('status')) {
                return;
            }

            $blocker = $this->route('blocker');
            if (! $blocker instanceof Blocker) {
                return;
            }

            $newStatus = $this->input('status');
            if ($blocker->status->isTerminal() && $newStatus !== $blocker->status->value) {
                $validator->errors()->add(
                    'status',
                    'Test case đã giải quyết hoặc đã đóng — không thể đổi sang trạng thái khác.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'evidence_links.*.url.url' => 'Link dẫn chứng phải là URL hợp lệ (https://…).',
            'evidence_links.*.url.required' => 'Mỗi dòng dẫn chứng cần có địa chỉ link.',
        ];
    }
}
