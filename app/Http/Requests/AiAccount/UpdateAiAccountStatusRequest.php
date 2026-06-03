<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('aiAccount'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AiAccountStatus::values())],
            'expiry_date' => ['nullable', 'date'],
            'sync_expiry_on_expire' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'expiry_date.date' => 'Ngày hết hạn không hợp lệ.',
        ];
    }

    public function account(): AiAccount
    {
        return $this->route('aiAccount');
    }
}
