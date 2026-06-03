<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiAccount::class);
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:255'],
            'license_type' => ['required', 'string', 'max:64'],
            'license_key' => ['nullable', 'string', 'max:512'],
            'group_function' => ['required', Rule::in(AiAccountGroupFunction::values())],
            'email_registered' => ['required', 'email', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after:purchase_date'],
            'cost_amount' => ['required', 'integer', 'min:1'],
            'cost_unit' => ['required', Rule::in(AiAccountCostUnit::values())],
            'seats' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'notify_before_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['cancelled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'tool_name.required' => 'Vui lòng nhập tên công cụ AI.',
            'email_registered.email' => 'Email đăng ký không hợp lệ.',
            'expiry_date.after' => 'Ngày hết hạn phải sau ngày mua.',
            'cost_amount.min' => 'Chi phí phải lớn hơn 0.',
        ];
    }
}
