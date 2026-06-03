<?php

namespace App\Http\Requests\AiAccount;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAiPurchaseProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('proposal'));
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
