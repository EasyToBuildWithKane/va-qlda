<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;

class UpdateAiPurchaseProposalRequest extends StoreAiPurchaseProposalRequest
{
    public function authorize(): bool
    {
        /** @var AiPurchaseProposal $proposal */
        $proposal = $this->route('proposal');

        return $this->user()->can('update', $proposal);
    }
}
