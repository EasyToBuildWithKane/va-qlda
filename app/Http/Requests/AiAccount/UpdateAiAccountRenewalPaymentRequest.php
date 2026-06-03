<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiAccountRenewalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AiAccount $aiAccount */
        $aiAccount = $this->route('aiAccount');

        return $this->user()->can('updateRenewalPayment', $aiAccount);
    }

    public function rules(): array
    {
        return [
            'renewal_payment_status' => ['required', Rule::in(AiAccountRenewalPaymentStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'renewal_payment_status.required' => 'Vui lòng chọn trạng thái thanh toán.',
            'renewal_payment_status.in' => 'Trạng thái thanh toán không hợp lệ.',
        ];
    }
}
