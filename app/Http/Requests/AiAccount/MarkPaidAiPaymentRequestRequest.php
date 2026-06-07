<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaidAiPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('markPaid', $this->route('paymentRequest'));
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date'],
            'actual_amount' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.date' => 'Ngày thanh toán không hợp lệ.',
            'actual_amount.integer' => 'Số tiền thực tế phải là số nguyên.',
            'actual_amount.min' => 'Số tiền thực tế phải lớn hơn 0.',
        ];
    }
}
