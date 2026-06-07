<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiAnalyticsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AiAccount::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:120'],
            'group_function' => ['nullable', Rule::in(array_merge(['all'], AiAccountGroupFunction::values()))],
            'tool' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_merge(['all'], AiAccountStatus::values()))],
            'lifecycle_status' => ['nullable', Rule::in(array_merge(['all'], AiAccountLifecycleStatus::values()))],
            'proposal_status' => ['nullable', Rule::in(array_merge(['all'], AiPurchaseProposalStatus::values()))],
            'proposer' => ['nullable', 'string', 'max:120'],
            'purchase_from' => ['nullable', 'date'],
            'purchase_to' => ['nullable', 'date'],
            'expiry_from' => ['nullable', 'date'],
            'expiry_to' => ['nullable', 'date'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'cost_min' => ['nullable', 'integer', 'min:0'],
            'cost_max' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cost_min.integer' => 'Chi phí tối thiểu không hợp lệ.',
            'cost_max.integer' => 'Chi phí tối đa không hợp lệ.',
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->validated();
    }
}
