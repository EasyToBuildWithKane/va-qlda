<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAiPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('paymentRequest'));
    }

    public function rules(): array
    {
        return [];
    }
}
