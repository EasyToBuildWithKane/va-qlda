<?php

namespace App\Http\Requests\WorkspaceConfig;

use App\Support\WorkspaceConfig\WorkspaceScopeResolver;
use Illuminate\Foundation\Http\FormRequest;

class BulkEnsureWorkspaceProfileRequest extends FormRequest
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
            'codes' => ['required', 'array', 'min:1', 'max:50'],
            'codes.*' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codes.required' => 'Chọn ít nhất một phòng ban để kích hoạt.',
            'codes.array' => 'Danh sách mã phòng ban không hợp lệ.',
            'codes.min' => 'Chọn ít nhất một phòng ban để kích hoạt.',
            'codes.max' => 'Mỗi lần chỉ kích hoạt tối đa 50 phòng ban.',
            'codes.*.required' => 'Mã phòng ban không được để trống.',
            'codes.*.string' => 'Mã phòng ban không hợp lệ.',
            'codes.*.max' => 'Mã phòng ban quá dài.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $codes = $this->input('codes');
        if (! is_array($codes)) {
            return;
        }

        $normalized = [];
        foreach ($codes as $code) {
            if (! is_string($code) && ! is_numeric($code)) {
                continue;
            }
            $trim = trim((string) $code);
            if ($trim === '') {
                continue;
            }
            $normalized[strtolower($trim)] = $trim;
        }

        $this->merge(['codes' => array_values($normalized)]);
    }
}
