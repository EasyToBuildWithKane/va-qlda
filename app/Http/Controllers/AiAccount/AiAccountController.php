<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\RenewAiAccountRequest;
use App\Http\Requests\AiAccount\StoreAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountRenewalPaymentRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountStatusRequest;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiAccountCostSummaryBuilder;
use App\Services\AiAccount\AiAccountFromProposalCreator;
use App\Services\AiAccount\AiAccountGrouper;
use App\Services\AiAccount\AiAccountReminderService;
use App\Services\AiAccount\AiAccountStatusSync;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Services\AiAccount\AiWorkflowMetricsBuilder;
use App\Support\EmployeePickerMapper;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountRenewalPaymentStatus;
use App\Support\Enums\AiAccountStatus;
use App\Support\Enums\AiPurchaseProposalStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAccountController extends Controller
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
        private readonly AiAccountCostSummaryBuilder $costSummaryBuilder,
        private readonly AiAccountStatusSync $statusSync,
        private readonly AiAccountReminderService $reminderService,
        private readonly AiPurchaseProposalPresenter $proposalPresenter,
        private readonly AiAccountFromProposalCreator $fromProposalCreator,
        private readonly AiWorkflowMetricsBuilder $workflowMetrics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $accounts = $this->loadAndSyncAccounts();
        $search = $request->query('search');
        $payload = $this->grouper->grouped($accounts, is_string($search) ? $search : null, $request->user());
        $summary = $this->costSummaryBuilder->build($accounts);

        return response()->json([
            'success' => true,
            'data' => [
                ...$payload,
                'summary_cards' => $summary['cards'],
                'proposal_counts' => $this->proposalPresenter->aggregateCounts(),
                'awaiting_account_count' => $this->proposalPresenter->awaitingAccountCount(),
                'workflow_metrics' => $this->workflowMetrics->build(),
            ],
            'meta' => [
                'options' => [
                    'group_function' => AiAccountGroupFunction::options(),
                    'cost_unit' => AiAccountCostUnit::options(),
                    'license_types' => config('ai_accounts.license_types', []),
                    'status' => AiAccountStatus::options(),
                ],
            ],
        ]);
    }

    public function searchEmployees(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $id = $request->query('id');
        $idInt = is_numeric($id) ? (int) $id : null;
        $q = $request->query('q');
        $query = is_string($q) ? $q : '';

        $employees = EmployeePickerMapper::search(
            $query,
            40,
            $idInt,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'employees' => $employees,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $accounts = $this->loadAndSyncAccounts();
        $proposals = AiPurchaseProposal::query()
            ->with(['creator.employee', 'reviewer.employee'])
            ->orderByDesc('created_at')
            ->get();

        $summary = $this->costSummaryBuilder->build($accounts);
        $summary['proposals'] = $this->proposalPresenter->list($proposals, $request->user());
        $summary['proposal_counts'] = $this->proposalPresenter->counts($proposals);
        $summary['workflow_metrics'] = $this->workflowMetrics->build();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function show(Request $request, AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('view', $aiAccount);
        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
        ]);
    }

    public function store(StoreAiAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $proposal = $request->proposal();

        $account = $this->fromProposalCreator->create($proposal, [
            'email_registered' => $validated['email_registered'],
            'login_password' => $validated['password'] ?? null,
            'notify_before_days' => $validated['notify_before_days'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'creator' => $request->user(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($account, $request->user())],
            'message' => 'Đã lập tài khoản AI từ phiếu đề xuất.',
        ], 201);
    }

    public function update(UpdateAiAccountRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();
        $data = [
            'email_registered' => $validated['email_registered'],
            'notify_before_days' => $validated['notify_before_days'] ?? $aiAccount->notify_before_days,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->user()->isAdminTier() && ! empty($validated['password'])) {
            $data['login_password'] = $validated['password'];
        }

        if ($request->user()->can('updateStatus', $aiAccount)) {
            if (! empty($validated['status'] ?? null)) {
                $this->applyManualStatus(
                    $aiAccount,
                    AiAccountStatus::from($validated['status']),
                    $validated['expiry_date'] ?? null,
                    (bool) ($validated['sync_expiry_on_expire'] ?? true),
                );
                $aiAccount->refresh();
            } elseif (! empty($validated['expiry_date'] ?? null) || isset($validated['purchase_date'])) {
                $dateUpdates = [];
                if (! empty($validated['expiry_date'] ?? null)) {
                    $dateUpdates['expiry_date'] = Carbon::parse($validated['expiry_date'])->startOfDay();
                }
                if (isset($validated['purchase_date'])) {
                    $dateUpdates['purchase_date'] = $validated['purchase_date'];
                }
                $aiAccount->update($dateUpdates);
                if ($aiAccount->status_locked_at === null) {
                    $this->statusSync->syncAndSave($aiAccount);
                }
                $aiAccount->refresh();
            }
        }

        $aiAccount->update($data);
        if ($aiAccount->status_locked_at === null) {
            $this->statusSync->syncAndSave($aiAccount);
        }

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã lưu thành công.',
        ]);
    }

    public function updateStatus(UpdateAiAccountStatusRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();

        $this->applyManualStatus(
            $aiAccount,
            AiAccountStatus::from($validated['status']),
            $validated['expiry_date'] ?? null,
            (bool) ($validated['sync_expiry_on_expire'] ?? true),
        );

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã cập nhật trạng thái tài khoản.',
        ]);
    }

    public function destroy(AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('delete', $aiAccount);
        $name = $aiAccount->tool_name;

        $linkedProposal = AiPurchaseProposal::query()
            ->where('ai_account_id', $aiAccount->id)
            ->first();

        $aiAccount->delete();

        if ($linkedProposal !== null) {
            $linkedProposal->update([
                'ai_account_id' => null,
                'status' => AiPurchaseProposalStatus::Expired,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Đã xoá {$name}.",
        ]);
    }

    public function renew(RenewAiAccountRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $validated = $request->validated();
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $months = (int) $validated['period_months'];

        $newExpiry = $start->copy()->addMonths($months);

        $aiAccount->update([
            'purchase_date' => $start,
            'expiry_date' => $newExpiry,
            'cost_amount' => (int) $validated['new_cost'],
            'renewal_payment_status' => AiAccountRenewalPaymentStatus::Unpaid,
            'renewal_paid_at' => null,
            'last_payment_reminded_at' => null,
        ]);

        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã gia hạn tài khoản.',
        ]);
    }

    public function updateRenewalPayment(
        UpdateAiAccountRenewalPaymentRequest $request,
        AiAccount $aiAccount,
    ): JsonResponse {
        $status = AiAccountRenewalPaymentStatus::from($request->validated('renewal_payment_status'));

        $aiAccount->update([
            'renewal_payment_status' => $status,
            'renewal_paid_at' => $status === AiAccountRenewalPaymentStatus::Paid ? now() : null,
            'last_payment_reminded_at' => $status === AiAccountRenewalPaymentStatus::Paid
                ? null
                : $aiAccount->last_payment_reminded_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => $status === AiAccountRenewalPaymentStatus::Paid
                ? 'Đã ghi nhận thanh toán gia hạn.'
                : 'Đã đánh dấu chưa thanh toán — sẽ nhận email nhắc nếu quá hạn.',
        ]);
    }

    public function triggerReminder(Request $request): JsonResponse
    {
        $this->authorize('triggerReminder', AiAccount::class);

        $expiryCount = $this->reminderService->sendDueReminders();
        $paymentCount = $this->reminderService->sendUnpaidRenewalReminders();
        $count = $expiryCount + $paymentCount;

        return response()->json([
            'success' => true,
            'data' => [
                'sent' => $count,
                'expiry_sent' => $expiryCount,
                'payment_sent' => $paymentCount,
            ],
            'message' => $count > 0
                ? "Đã gửi {$count} nhắc nhở (hết hạn: {$expiryCount}, chưa thanh toán: {$paymentCount})."
                : 'Không có tài khoản nào cần nhắc hôm nay.',
        ]);
    }

    private function loadAndSyncAccounts()
    {
        AiAccount::purgeOrphanedFromProposal();

        $accounts = AiAccount::query()
            ->visibleInRegistry()
            ->with('purchaseProposal')
            ->orderBy('tool_name')
            ->get();
        $this->statusSync->syncCollection($accounts);

        return $accounts
            ->map(fn (AiAccount $a) => $a->fresh(['purchaseProposal']))
            ->filter()
            ->values();
    }

    private function applyManualStatus(
        AiAccount $account,
        AiAccountStatus $status,
        ?string $expiryDate = null,
        bool $syncExpiryOnExpire = true,
    ): void {
        $data = [
            'status' => $status,
            'status_locked_at' => now(),
        ];

        if ($expiryDate !== null && $expiryDate !== '') {
            $data['expiry_date'] = Carbon::parse($expiryDate)->startOfDay();
        } elseif ($syncExpiryOnExpire && $status === AiAccountStatus::Expired) {
            $data['expiry_date'] = now()->startOfDay();
        }

        $account->update($data);
    }

    private function accountRow(?AiAccount $account, ?\App\Models\SystemAccount $viewer = null): ?array
    {
        if (! $account) {
            return null;
        }

        $account->loadMissing('purchaseProposal');

        return $this->grouper->accountPayload($account, $viewer);
    }
}
