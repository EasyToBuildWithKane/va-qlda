<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountCostUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmProposalScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm', $this->route('scan'))
            && $this->user()->can('create', AiPurchaseProposal::class);
    }

    public function rules(): array
    {
        return [
            'subject_about' => ['required', 'string', 'max:500'],
            'proposer_name' => ['required', 'string', 'max:255'],
            'proposer_position' => ['nullable', 'string', 'max:255'],
            'proposer_department' => ['nullable', 'string', 'max:255'],
            'send_to' => ['nullable', 'string', 'max:500'],
            'proposal_content' => ['required', 'string', 'min:20', 'max:8000'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'cost_amount' => ['required', 'integer', 'min:1'],
            'cost_unit' => ['nullable', Rule::in(AiAccountCostUnit::values())],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_about.required' => 'Vui lòng nhập trích yếu (Về việc) trước khi lưu.',
            'proposer_name.required' => 'Vui lòng nhập họ tên người đề xuất.',
            'proposal_content.required' => 'Vui lòng nhập nội dung đề xuất.',
            'proposal_content.min' => 'Nội dung đề xuất cần mô tả đầy đủ hơn (tối thiểu 20 ký tự).',
            'cost_amount.required' => 'Vui lòng nhập chi phí đề xuất (OCR không đọc được — kiểm tra lại phiếu).',
            'cost_amount.min' => 'Chi phí đề xuất phải lớn hơn 0.',
        ];
    }
}
