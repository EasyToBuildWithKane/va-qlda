<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('renew', $this->route('aiAccount'));
    }

    public function rules(): array
    {
        return [
            'period_months' => ['required', 'integer', Rule::in([1, 3, 6, 12])],
            'new_cost' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_months.required' => 'Vui lòng chọn chu kỳ gia hạn.',
            'new_cost.min' => 'Chi phí gia hạn phải lớn hơn 0.',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
        ];
    }
}
