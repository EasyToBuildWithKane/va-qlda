<?php

namespace App\Services\AiAccount;

use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Support\Collection;

class AiPurchaseProposalPresenter
{
    public function __construct(
        private readonly AiAccountCostCalculator $costCalculator,
    ) {}

    /**
     * @param  Collection<int, AiPurchaseProposal>  $proposals
     * @return array<int, array<string, mixed>>
     */
    public function list(Collection $proposals, ?SystemAccount $viewer = null): array
    {
        return $proposals
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (AiPurchaseProposal $p) => $this->row($p, $viewer))
            ->all();
    }

    /**
     * @param  Collection<int, AiPurchaseProposal>  $proposals
     * @return array<string, int>
     */
    public function counts(Collection $proposals): array
    {
        $counts = [];
        foreach (AiPurchaseProposalStatus::cases() as $status) {
            $counts[$status->value] = $proposals->where('status', $status)->count();
        }
        $counts['total'] = $proposals->count();

        return $counts;
    }

    /** @return array<string, int> */
    public function aggregateCounts(): array
    {
        $raw = AiPurchaseProposal::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $counts = [];
        foreach (AiPurchaseProposalStatus::cases() as $status) {
            $counts[$status->value] = (int) ($raw[$status->value] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function awaitingAccountCount(): int
    {
        return AiPurchaseProposal::query()
            ->whereNull('ai_account_id')
            ->whereIn('status', array_map(
                fn (AiPurchaseProposalStatus $s) => $s->value,
                AiAccountCountableProposalCost::countableStatuses(),
            ))
            ->whereHas('paymentRequest', fn ($q) => $q->whereIn('status', [
                AiPaymentRequestStatus::Approved->value,
                AiPaymentRequestStatus::Paid->value,
            ]))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(AiPurchaseProposal $proposal, ?SystemAccount $viewer = null): array
    {
        $proposal->loadMissing(['creator.employee', 'reviewer.employee', 'paymentRequest.creator', 'paymentRequest.reviewer']);
        $monthly = $this->costCalculator->monthlyAmount($proposal->cost_amount, $proposal->cost_unit);

        $creatorName = $proposal->creator?->employee?->full_name
            ?? $proposal->creator?->username
            ?? '—';

        $reviewerName = $proposal->reviewer?->employee?->full_name
            ?? $proposal->reviewer?->username;

        $purchaseType = $proposal->purchase_type?->value ?? 'new';

        return [
            'id' => $proposal->id,
            'proposal_code' => $proposal->proposal_code,
            'proposal_type' => $proposal->proposal_type?->value,
            'proposal_type_label' => $proposal->proposal_type?->labelVi(),
            'subject_about' => $proposal->subject_about,
            'send_to' => $proposal->send_to,
            'tool_name' => $proposal->tool_name,
            'vendor_name' => $proposal->vendor_name,
            'vendor_website' => $proposal->vendor_website,
            'proposer_name' => $proposal->proposer_name,
            'proposer_position' => $proposal->proposer_position,
            'proposer_department' => $proposal->proposer_department,
            'proposal_content' => $proposal->proposal_content,
            'description' => $proposal->description,
            'reason_for_proposal' => $proposal->reason_for_proposal,
            'expected_benefit' => $proposal->expected_benefit,
            'objectives' => $proposal->objectives,
            'quantity' => $proposal->quantity,
            'staff_count' => $proposal->staff_count,
            'users_list' => $proposal->users_list ?? [],
            'department_using' => $proposal->department_using,
            'recipient_name' => $proposal->recipient_name,
            'recipient_position' => $proposal->recipient_position,
            'recipient_email' => $proposal->recipient_email,
            'recipient_phone' => $proposal->recipient_phone,
            'purchase_type' => $purchaseType,
            'purchase_type_label' => $proposal->purchase_type?->labelVi() ?? 'Mua mới',
            'registration_email' => $proposal->registration_email,
            'planned_use_date' => $proposal->planned_use_date?->format('Y-m-d'),
            'start_date' => $proposal->start_date?->format('Y-m-d'),
            'end_date' => $proposal->end_date?->format('Y-m-d'),
            'attachment_paths' => $proposal->attachment_paths ?? [],
            'export_pdf_url' => route('api.ai-accounts.proposals.export.pdf', ['proposal' => $proposal->id]),
            'export_payment_request_pdf_url' => route('api.ai-accounts.proposals.export.payment-request.pdf', ['proposal' => $proposal->id]),
            'group_function' => $proposal->group_function->value,
            'group_dot_color' => $proposal->group_function->dotColor(),
            'group_label' => $this->groupLabel($proposal->group_function),
            'license_type' => $proposal->license_type,
            'cost_amount' => $proposal->cost_amount,
            'actual_cost' => $proposal->actual_cost,
            'cost_unit' => $proposal->cost_unit->value,
            'cost_unit_label' => $proposal->cost_unit->labelVi(),
            'cost_monthly' => $monthly,
            'seats' => $proposal->seats,
            'justification' => $proposal->justification,
            'status' => $proposal->status->value,
            'status_label' => $proposal->status->labelVi(),
            'status_color' => $proposal->status->badgeColor(),
            'rejection_reason' => $proposal->rejection_reason,
            'review_notes' => $proposal->review_notes,
            'created_by_name' => $creatorName,
            'reviewed_by_name' => $reviewerName,
            'reviewed_at' => $proposal->reviewed_at?->format('Y-m-d H:i'),
            'created_at' => $proposal->created_at?->format('Y-m-d H:i'),
            'can_review' => $proposal->status === AiPurchaseProposalStatus::Pending,
            'can_edit' => $this->canEdit($proposal, $viewer),
            'can_edit_notes' => in_array($proposal->status, [
                AiPurchaseProposalStatus::Approved,
                AiPurchaseProposalStatus::Rejected,
            ], true),
            'can_delete' => $viewer?->can('delete', $proposal) ?? false,
            'ai_account_id' => $proposal->ai_account_id,
            'payment_request' => $this->paymentRequestPayload($proposal->paymentRequest, $viewer),
            'awaiting_account' => $this->isAwaitingAccount($proposal),
        ];
    }

    private function isAwaitingAccount(AiPurchaseProposal $proposal): bool
    {
        if ($proposal->ai_account_id !== null) {
            return false;
        }
        $isProposalApproved = in_array($proposal->status, [
            AiPurchaseProposalStatus::Approved,
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ], true);
        if (! $isProposalApproved) {
            return false;
        }
        $pr = $proposal->paymentRequest;

        return $pr !== null && in_array($pr->status, [
            AiPaymentRequestStatus::Approved,
            AiPaymentRequestStatus::Paid,
        ], true);
    }

    /** @return array<string, mixed>|null */
    private function paymentRequestPayload(?AiPaymentRequest $pr, ?SystemAccount $viewer = null): ?array
    {
        if ($pr === null) {
            return null;
        }

        $creatorName = $pr->creator?->employee?->full_name ?? $pr->creator?->username ?? '—';
        $reviewerName = $pr->reviewer?->employee?->full_name ?? $pr->reviewer?->username;

        return [
            'id' => $pr->id,
            'payment_request_code' => $pr->payment_request_code,
            'amount' => $pr->amount,
            'status' => $pr->status->value,
            'status_label' => $pr->status->labelVi(),
            'status_color' => $pr->status->badgeColor(),
            'created_by_name' => $creatorName,
            'reviewed_by_name' => $reviewerName,
            'reviewed_at' => $pr->reviewed_at?->format('Y-m-d H:i'),
            'paid_at' => $pr->paid_at?->format('Y-m-d H:i'),
            'rejection_reason' => $pr->rejection_reason,
            'created_at' => $pr->created_at?->format('Y-m-d H:i'),
            'can_review' => $viewer?->can('review', $pr) ?? false,
            'can_mark_paid' => $viewer?->can('markPaid', $pr) ?? false,
        ];
    }

    /**
     * Phiếu đã duyệt, chờ lập tài khoản trên trang Tài khoản AI.
     *
     * @return array<string, mixed>
     */
    public function awaitingAccountOption(AiPurchaseProposal $proposal): array
    {
        $email = trim((string) ($proposal->registration_email ?: $proposal->recipient_email ?: ''));
        $unit = $proposal->cost_unit;
        $billingHint = $unit->value === 'yearly'
            ? (string) config('ai_accounts.defaults.billing_hint_yearly')
            : (string) config('ai_accounts.defaults.billing_hint_monthly');

        return [
            'id' => $proposal->id,
            'proposal_code' => $proposal->proposal_code,
            'tool_name' => $proposal->tool_name,
            'label' => trim(($proposal->proposal_code ?? '').' — '.$proposal->tool_name, ' —'),
            'group_function' => $proposal->group_function->value,
            'group_label' => $this->groupLabel($proposal->group_function),
            'license_type' => $proposal->license_type,
            'cost_amount' => $proposal->cost_amount,
            'cost_unit' => $proposal->cost_unit->value,
            'cost_unit_label' => $unit->labelVi(),
            'cost_monthly' => $this->costCalculator->monthlyAmount($proposal->cost_amount, $unit),
            'registration_email' => $email !== '' ? $email : null,
            'planned_use_date' => $proposal->planned_use_date?->format('Y-m-d'),
            'start_date' => $proposal->start_date?->format('Y-m-d'),
            'end_date' => $proposal->end_date?->format('Y-m-d'),
            'notify_before_days_suggested' => (int) config('ai_accounts.defaults.notify_before_days', 14),
            'notify_hint' => (string) config('ai_accounts.defaults.notify_hint'),
            'billing_hint' => $billingHint,
            'notes_suggested' => "Tài khoản từ phiếu {$proposal->proposal_code}. Chu kỳ: {$unit->labelVi()}.",
        ];
    }

    private function canEdit(AiPurchaseProposal $proposal, ?SystemAccount $viewer): bool
    {
        if ($viewer === null || $proposal->status !== AiPurchaseProposalStatus::Pending) {
            return false;
        }

        if ($viewer->role === SystemRole::Admin) {
            return true;
        }

        return $proposal->created_by === $viewer->id;
    }

    private function groupLabel(AiAccountGroupFunction $group): string
    {
        foreach (AiAccountGroupFunction::options() as $opt) {
            if ($opt['value'] === $group->value) {
                return $opt['label'];
            }
        }

        return $group->value;
    }
}
