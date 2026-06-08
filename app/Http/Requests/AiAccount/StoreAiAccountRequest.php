<?php

namespace App\Http\Requests\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AiAccount::class);
    }

    public function rules(): array
    {
        return [
            'proposal_id' => [
                'required',
                'uuid',
                Rule::exists('ai_purchase_proposals', 'id')->where(function ($q) {
                    $q->whereIn('status', [
                        AiPurchaseProposalStatus::Approved->value,
                        AiPurchaseProposalStatus::Purchased->value,
                        AiPurchaseProposalStatus::Active->value,
                    ]);
                }),
            ],
            'email_registered' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'notify_before_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('password') && $this->user()->role !== SystemRole::Admin) {
                $validator->errors()->add('password', 'Chỉ quản trị viên được lưu mật khẩu đăng nhập.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $proposalId = $this->input('proposal_id');
            if (! is_string($proposalId) || $proposalId === '') {
                return;
            }

            $proposal = AiPurchaseProposal::query()->find($proposalId);
            if ($proposal === null) {
                return;
            }

            if (! $proposal->hasRemainingAccountSlots()) {
                $validator->errors()->add('proposal_id', 'Phiếu không hợp lệ hoặc đã được lập tài khoản.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'proposal_id.required' => 'Vui lòng chọn phiếu đề xuất đã duyệt.',
            'proposal_id.exists' => 'Phiếu không hợp lệ hoặc đã được lập tài khoản.',
            'email_registered.required' => 'Vui lòng nhập email đăng ký.',
            'email_registered.email' => 'Email đăng ký không hợp lệ.',
        ];
    }

    public function proposal(): AiPurchaseProposal
    {
        return AiPurchaseProposal::query()->findOrFail($this->validated('proposal_id'));
    }
}
