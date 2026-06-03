<?php

namespace App\Http\Requests\AiAccount;

use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('aiAccount'));
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['sometimes', 'required', 'string', 'max:255'],
            'license_type' => ['sometimes', 'required', 'string', 'max:64'],
            'license_key' => ['nullable', 'string', 'max:512'],
            'group_function' => ['sometimes', 'required', Rule::in(AiAccountGroupFunction::values())],
            'email_registered' => ['sometimes', 'required', 'email', 'max:255'],
            'purchase_date' => ['sometimes', 'required', 'date'],
            'expiry_date' => ['sometimes', 'required', 'date', 'after:purchase_date'],
            'cost_amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'cost_unit' => ['sometimes', 'required', Rule::in(AiAccountCostUnit::values())],
            'seats' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'notify_before_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(AiAccountStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'email_registered.email' => 'Email đăng ký không hợp lệ.',
            'expiry_date.after' => 'Ngày hết hạn phải sau ngày mua.',
            'cost_amount.min' => 'Chi phí phải lớn hơn 0.',
        ];
    }
}
