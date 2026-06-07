<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountLifecycleStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AiAccountFromProposalCreator
{
    public function __construct(
        private readonly AiAccountStatusSync $statusSync,
    ) {}

    /**
     * @param  array{email_registered: string, login_password?: string|null, notify_before_days?: int, notes?: string|null, creator?: SystemAccount|null}  $input
     */
    public function create(AiPurchaseProposal $proposal, array $input): AiAccount
    {
        $this->assertProposalReady($proposal);

        $purchaseDate = $this->resolvePurchaseDate($proposal);
        $expiryDate = $this->resolveExpiryDate($proposal, $purchaseDate);
        $creator = $input['creator'] ?? null;

        $account = AiAccount::create([
            'tool_name' => $proposal->tool_name,
            'license_type' => $proposal->license_type,
            'group_function' => $proposal->group_function,
            'email_registered' => trim($input['email_registered']),
            'login_password' => $input['login_password'] ?? null,
            'purchase_date' => $purchaseDate,
            'expiry_date' => $expiryDate,
            'cost_amount' => $proposal->cost_amount,
            'cost_unit' => $proposal->cost_unit,
            'seats' => $proposal->seats,
            'status' => AiAccountStatus::Active,
            'notify_before_days' => (int) ($input['notify_before_days'] ?? config('ai_accounts.defaults.notify_before_days', 14)),
            'notes' => $input['notes'] ?? $this->defaultNotes($proposal),
            'lifecycle_status' => AiAccountLifecycleStatus::InUse,
            'purchased_by' => $creator?->id,
            'actual_purchase_cost' => $proposal->actual_cost ?? $proposal->cost_amount,
            'allocated_at' => $purchaseDate,
            'allocated_to_name' => $proposal->recipient_name ?? $proposal->proposer_name,
        ]);

        $this->statusSync->syncAndSave($account);

        $proposal->update([
            'ai_account_id' => $account->id,
            'actual_cost' => $proposal->actual_cost ?? $proposal->cost_amount,
        ]);

        return $account->fresh();
    }

    private function assertProposalReady(AiPurchaseProposal $proposal): void
    {
        if (! in_array($proposal->status, [
            AiPurchaseProposalStatus::Approved,
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ], true)) {
            throw ValidationException::withMessages([
                'proposal_id' => 'Chỉ phiếu đề xuất đã duyệt mới được lập tài khoản.',
            ]);
        }

        if ($proposal->ai_account_id) {
            throw ValidationException::withMessages([
                'proposal_id' => 'Phiếu này đã có tài khoản AI gắn kèm.',
            ]);
        }

        $proposal->loadMissing('paymentRequest');
        $pr = $proposal->paymentRequest;

        if ($pr === null) {
            throw ValidationException::withMessages([
                'proposal_id' => 'Phiếu chưa có đề nghị thanh toán. Vui lòng tạo và duyệt ĐNTT trước khi lập tài khoản.',
            ]);
        }

        if (! in_array($pr->status, [AiPaymentRequestStatus::Approved, AiPaymentRequestStatus::Paid], true)) {
            throw ValidationException::withMessages([
                'proposal_id' => 'Đề nghị thanh toán chưa được duyệt. Vui lòng duyệt ĐNTT trước khi lập tài khoản.',
            ]);
        }
    }

    private function resolvePurchaseDate(AiPurchaseProposal $proposal): Carbon
    {
        if ($proposal->start_date) {
            return $proposal->start_date->copy()->startOfDay();
        }
        if ($proposal->planned_use_date) {
            return $proposal->planned_use_date->copy()->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolveExpiryDate(AiPurchaseProposal $proposal, Carbon $purchaseDate): Carbon
    {
        if ($proposal->end_date && $proposal->end_date->greaterThan($purchaseDate)) {
            return $proposal->end_date->copy()->startOfDay();
        }

        return $purchaseDate->copy()->addYear();
    }

    private function defaultNotes(AiPurchaseProposal $proposal): string
    {
        $code = $proposal->proposal_code ?? $proposal->id;
        $unit = $proposal->cost_unit->labelVi();

        return "Tài khoản từ phiếu {$code}. Chu kỳ thanh toán: {$unit}.";
    }
}
