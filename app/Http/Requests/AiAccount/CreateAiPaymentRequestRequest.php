<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;
use Illuminate\Foundation\Http\FormRequest;

class CreateAiPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AiPurchaseProposal $proposal */
        $proposal = $this->route('proposal');

        return $this->user()->can('create', [\App\Models\AiPaymentRequest::class, $proposal]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
        ];
    }
}
