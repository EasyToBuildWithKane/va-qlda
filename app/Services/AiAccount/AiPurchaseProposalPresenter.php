<?php

namespace App\Services\AiAccount;

use App\Models\AiPaymentRequest;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPaymentRequestStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
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
            ->withCount('linkedAccounts')
            ->whereIn('status', array_map(
                fn (AiPurchaseProposalStatus $s) => $s->value,
                AiAccountCountableProposalCost::countableStatuses(),
            ))
            ->whereHas('paymentRequest', fn ($q) => $q->whereIn('status', [
                AiPaymentRequestStatus::Approved->value,
                AiPaymentRequestStatus::Paid->value,
            ]))
            ->get()
            ->filter(fn (AiPurchaseProposal $p) => $p->hasRemainingAccountSlots())
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
        $paymentRequest = $this->paymentRequestPayload($proposal->paymentRequest, $viewer);
        $overall = $this->overallWorkflowStatus($proposal, $paymentRequest);

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
            'purchase_type_label' => $proposal->purchase_type->labelVi(),
            'registration_email' => $proposal->registration_email,
            'registration_emails' => $proposal->registrationEmailsList(),
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
            'payment_request' => $paymentRequest,
            'payment_requests' => $paymentRequest !== null ? [$paymentRequest] : [],
            'payment_request_status_key' => $this->paymentRequestStatusKey($paymentRequest),
            'overall_status' => $overall,
            'workflow_timeline' => $this->workflowTimeline($proposal, $paymentRequest),
            'approval_history_tooltip' => $this->approvalHistoryTooltip($proposal, $paymentRequest),
            'awaiting_account' => $this->isAwaitingAccount($proposal),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $paymentRequest
     * @return array{key: string, label: string, color: string}
     */
    private function overallWorkflowStatus(AiPurchaseProposal $proposal, ?array $paymentRequest): array
    {
        if ($proposal->status === AiPurchaseProposalStatus::Rejected) {
            return ['key' => 'pdx_rejected', 'label' => 'PĐX từ chối', 'color' => 'rose'];
        }

        if (in_array($proposal->status, [
            AiPurchaseProposalStatus::Pending,
            AiPurchaseProposalStatus::Submitted,
            AiPurchaseProposalStatus::Draft,
        ], true)) {
            return ['key' => 'pdx_pending', 'label' => 'PĐX chờ duyệt', 'color' => 'amber'];
        }

        if ($paymentRequest === null) {
            return ['key' => 'dntt_not_created', 'label' => 'Chưa tạo ĐNTT', 'color' => 'slate'];
        }

        return match ($paymentRequest['status']) {
            AiPaymentRequestStatus::Pending->value => [
                'key' => 'dntt_pending',
                'label' => 'ĐNTT chờ duyệt',
                'color' => 'amber',
            ],
            AiPaymentRequestStatus::Rejected->value => [
                'key' => 'dntt_rejected',
                'label' => 'ĐNTT từ chối',
                'color' => 'rose',
            ],
            AiPaymentRequestStatus::Approved->value => [
                'key' => 'dntt_approved',
                'label' => 'ĐNTT đã duyệt',
                'color' => 'emerald',
            ],
            AiPaymentRequestStatus::Paid->value => $this->isAwaitingAccount($proposal)
                ? ['key' => 'paid_awaiting_account', 'label' => 'Đã thanh toán · chờ lập TK', 'color' => 'brand']
                : ['key' => 'paid', 'label' => 'Đã thanh toán', 'color' => 'blue'],
            default => ['key' => 'unknown', 'label' => '—', 'color' => 'slate'],
        };
    }

    /** @param  array<string, mixed>|null  $paymentRequest */
    private function paymentRequestStatusKey(?array $paymentRequest): string
    {
        if ($paymentRequest === null) {
            return 'not_created';
        }

        return $paymentRequest['status'];
    }

    /**
     * @param  array<string, mixed>|null  $paymentRequest
     * @return list<array<string, mixed>>
     */
    private function workflowTimeline(AiPurchaseProposal $proposal, ?array $paymentRequest): array
    {
        $events = [];

        if ($proposal->created_at) {
            $events[] = [
                'id' => 'pdx-created',
                'phase' => 'pdx',
                'at' => $proposal->created_at->format('Y-m-d H:i'),
                'title' => 'Tạo phiếu đề xuất',
                'detail' => $proposal->proposer_name ?: ($proposal->creator?->employee?->full_name ?? ''),
            ];
        }

        if ($proposal->reviewed_at && in_array($proposal->status, [
            AiPurchaseProposalStatus::Approved,
            AiPurchaseProposalStatus::Purchased,
            AiPurchaseProposalStatus::Active,
        ], true)) {
            $events[] = [
                'id' => 'pdx-approved',
                'phase' => 'pdx',
                'at' => $proposal->reviewed_at->format('Y-m-d H:i'),
                'title' => 'PĐX đã duyệt',
                'detail' => $proposal->reviewer?->employee?->full_name ?? $proposal->reviewer?->username ?? '',
            ];
        }

        if ($proposal->status === AiPurchaseProposalStatus::Rejected && $proposal->reviewed_at) {
            $events[] = [
                'id' => 'pdx-rejected',
                'phase' => 'pdx',
                'at' => $proposal->reviewed_at->format('Y-m-d H:i'),
                'title' => 'PĐX từ chối',
                'detail' => $proposal->rejection_reason ?? '',
            ];
        }

        if ($paymentRequest !== null) {
            if (! empty($paymentRequest['created_at'])) {
                $events[] = [
                    'id' => 'dntt-created',
                    'phase' => 'dntt',
                    'at' => $paymentRequest['created_at'],
                    'title' => 'Tạo đề nghị thanh toán',
                    'detail' => $paymentRequest['payment_request_code'] ?? '',
                ];
            }
            if (! empty($paymentRequest['reviewed_at']) && in_array($paymentRequest['status'], [
                AiPaymentRequestStatus::Approved->value,
                AiPaymentRequestStatus::Paid->value,
            ], true)) {
                $events[] = [
                    'id' => 'dntt-approved',
                    'phase' => 'dntt',
                    'at' => $paymentRequest['reviewed_at'],
                    'title' => 'ĐNTT đã duyệt',
                    'detail' => $paymentRequest['reviewed_by_name'] ?? '',
                ];
            }
            if (! empty($paymentRequest['reviewed_at']) && $paymentRequest['status'] === AiPaymentRequestStatus::Rejected->value) {
                $events[] = [
                    'id' => 'dntt-rejected',
                    'phase' => 'dntt',
                    'at' => $paymentRequest['reviewed_at'],
                    'title' => 'ĐNTT từ chối',
                    'detail' => $paymentRequest['rejection_reason'] ?? '',
                ];
            }
            if (! empty($paymentRequest['paid_at'])) {
                $events[] = [
                    'id' => 'dntt-paid',
                    'phase' => 'payment',
                    'at' => $paymentRequest['paid_at'],
                    'title' => 'Đã thanh toán',
                    'detail' => $paymentRequest['amount'] !== null
                        ? number_format((int) $paymentRequest['amount'], 0, ',', '.').' VNĐ'
                        : '',
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return $events;
    }

    /**
     * @param  array<string, mixed>|null  $paymentRequest
     */
    private function approvalHistoryTooltip(AiPurchaseProposal $proposal, ?array $paymentRequest): string
    {
        $lines = [];
        $lines[] = 'PĐX: '.$proposal->status->labelVi();
        if ($proposal->reviewed_at) {
            $who = $proposal->reviewer?->employee?->full_name ?? $proposal->reviewer?->username ?? '—';
            $lines[] = 'Duyệt PĐX: '.$who.' · '.$proposal->reviewed_at->format('d/m/Y H:i');
        }
        if ($paymentRequest === null) {
            $lines[] = 'ĐNTT: Chưa tạo';
        } else {
            $lines[] = 'ĐNTT: '.$paymentRequest['status_label'];
            if (! empty($paymentRequest['reviewed_at'])) {
                $lines[] = 'Duyệt ĐNTT: '.($paymentRequest['reviewed_by_name'] ?? '—')
                    .' · '.substr((string) $paymentRequest['reviewed_at'], 0, 16);
            }
            if (! empty($paymentRequest['paid_at'])) {
                $lines[] = 'Thanh toán: '.substr((string) $paymentRequest['paid_at'], 0, 16);
            }
        }

        return implode("\n", $lines);
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
            'payment_document_paths' => $pr->payment_document_paths ?? [],
        ];
    }

    /**
     * Phiếu đã duyệt, chờ lập tài khoản trên trang Tài khoản AI.
     *
     * @return array<string, mixed>
     */
    public function awaitingAccountOption(AiPurchaseProposal $proposal): array
    {
        $proposal->loadCount('linkedAccounts');
        $emails = $proposal->registrationEmailsList();
        $slotIndex = $proposal->provisionedAccountsCount();
        $suggested = trim((string) ($emails[$slotIndex] ?? ''));
        if ($suggested === '' || ! filter_var($suggested, FILTER_VALIDATE_EMAIL)) {
            $suggested = trim((string) ($proposal->registration_email ?: $proposal->recipient_email ?: ''));
        }
        $unit = $proposal->cost_unit;
        $billingHint = $unit->value === 'yearly'
            ? (string) config('ai_accounts.defaults.billing_hint_yearly')
            : (string) config('ai_accounts.defaults.billing_hint_monthly');

        return [
            'id' => $proposal->id,
            'proposal_code' => $proposal->proposal_code,
            'tool_name' => $proposal->tool_name,
            'label' => trim(
                ($proposal->proposal_code ?? '').' — '.$proposal->tool_name
                .($proposal->staffSlots() > 1
                    ? ' (TK '.($slotIndex + 1).'/'.$proposal->staffSlots().')'
                    : ''),
                ' —',
            ),
            'group_function' => $proposal->group_function->value,
            'group_label' => $this->groupLabel($proposal->group_function),
            'license_type' => $proposal->license_type,
            'cost_amount' => $proposal->cost_amount,
            'cost_unit' => $proposal->cost_unit->value,
            'cost_unit_label' => $unit->labelVi(),
            'cost_monthly' => $this->costCalculator->monthlyAmount($proposal->cost_amount, $unit),
            'registration_email' => $suggested !== '' ? $suggested : null,
            'registration_emails' => $emails,
            'staff_count' => $proposal->staffSlots(),
            'accounts_created' => $proposal->provisionedAccountsCount(),
            'account_slot_index' => $slotIndex,
            'account_slot_label' => ($slotIndex + 1).'/'.$proposal->staffSlots(),
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

        if ($viewer->isAdminTier()) {
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
