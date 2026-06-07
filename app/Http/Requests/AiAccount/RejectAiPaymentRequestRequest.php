<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class RejectAiPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('paymentRequest'));
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Vui lòng nhập lý do từ chối.',
            'rejection_reason.min' => 'Lý do từ chối phải có ít nhất 10 ký tự.',
        ];
    }
}
