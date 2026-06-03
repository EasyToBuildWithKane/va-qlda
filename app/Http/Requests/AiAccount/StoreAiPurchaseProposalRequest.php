<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiPurchaseProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiPurchaseProposal::class);
    }

    public function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:255'],
            'group_function' => ['required', Rule::in(AiAccountGroupFunction::values())],
            'license_type' => ['required', 'string', 'max:64'],
            'cost_amount' => ['required', 'integer', 'min:1'],
            'cost_unit' => ['required', Rule::in(AiAccountCostUnit::values())],
            'seats' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'justification' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tool_name.required' => 'Vui lòng nhập tên công cụ đề xuất.',
            'justification.required' => 'Vui lòng mô tả lý do đề xuất mua.',
            'justification.min' => 'Lý do đề xuất cần ít nhất :min ký tự.',
            'cost_amount.min' => 'Chi phí dự kiến phải lớn hơn 0.',
        ];
    }
}
