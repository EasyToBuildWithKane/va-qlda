<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class RejectAiPurchaseProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('proposal'));
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
            'rejection_reason.min' => 'Lý do từ chối cần ít nhất :min ký tự.',
        ];
    }
}
