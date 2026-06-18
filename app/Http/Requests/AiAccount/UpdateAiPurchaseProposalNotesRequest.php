<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiPurchaseProposalNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');
        if (! $proposal instanceof AiPurchaseProposal) {
            return false;
        }

        return $this->user()->isAdminTier()
            && in_array($proposal->status, [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Rejected,
            ], true);
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_notes.max' => 'Ghi chú không được vượt quá :max ký tự.',
        ];
    }
}
