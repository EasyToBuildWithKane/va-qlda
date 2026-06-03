<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseType;
use App\Support\Enums\ProposalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAiPurchaseProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiPurchaseProposal::class);
    }

    public function rules(): array
    {
        return [
            'proposal_type' => ['nullable', Rule::in(ProposalType::values())],
            'subject_about' => ['nullable', 'string', 'max:500'],
            'send_to' => ['nullable', 'string', 'max:500'],
            'tool_name' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_website' => ['nullable', 'string', 'max:500'],
            'group_function' => ['nullable', Rule::in(AiAccountGroupFunction::values())],
            'license_type' => ['nullable', 'string', 'max:64'],
            'cost_amount' => ['nullable', 'integer', 'min:0'],
            'cost_unit' => ['nullable', Rule::in(AiAccountCostUnit::values())],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'proposer_name' => ['nullable', 'string', 'max:255'],
            'proposer_position' => ['nullable', 'string', 'max:255'],
            'proposer_department' => ['nullable', 'string', 'max:255'],
            'proposal_content' => ['nullable', 'string', 'max:8000'],
            'description' => ['nullable', 'string', 'max:8000'],
            'reason_for_proposal' => ['nullable', 'string', 'max:3000'],
            'expected_benefit' => ['nullable', 'string', 'max:3000'],
            'objectives' => ['nullable', 'string', 'max:8000'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'staff_count' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'users_list' => ['nullable', 'array', 'max:200'],
            'users_list.*' => ['string', 'max:255'],
            'department_using' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_position' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:32'],
            'purchase_type' => ['nullable', Rule::in(AiPurchaseType::values())],
            'registration_email' => ['nullable', 'string', 'max:255'],
            'planned_use_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
