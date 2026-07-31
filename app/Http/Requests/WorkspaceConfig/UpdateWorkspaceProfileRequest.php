<?php

namespace App\Http\Requests\WorkspaceConfig;

use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(WorkspaceScopeResolver::class)->canManageAll($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(WorkspaceProfileStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.string' => 'Ghi chú không hợp lệ.',
            'notes.max' => 'Ghi chú tối đa 2000 ký tự.',
            'status.in' => 'Trạng thái workspace không hợp lệ.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('notes') && ! $this->has('status')) {
                $validator->errors()->add('notes', 'Cần gửi ghi chú hoặc trạng thái để cập nhật.');
            }
        });
    }
}
