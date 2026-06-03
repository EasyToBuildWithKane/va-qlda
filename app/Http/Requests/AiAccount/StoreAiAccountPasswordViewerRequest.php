<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAccountPasswordViewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePasswordViewers', AiAccount::class);
    }

    public function rules(): array
    {
        return [
            'system_account_id' => [
                'required',
                'integer',
                Rule::exists('system_accounts', 'id')->where(fn ($q) => $q->where('is_active', true)),
                Rule::unique('ai_account_password_viewers', 'system_account_id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'system_account_id.required' => 'Vui lòng chọn thành viên.',
            'system_account_id.unique' => 'Thành viên này đã có quyền xem mật khẩu.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = (int) $this->input('system_account_id');
            $target = SystemAccount::query()->find($id);
            if ($target && $target->role === SystemRole::Admin) {
                $validator->errors()->add('system_account_id', 'Quản trị viên đã có quyền xem mật khẩu mặc định.');
            }
        });
    }
}
