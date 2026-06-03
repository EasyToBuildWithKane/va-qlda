<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\RenewAiAccountRequest;
use App\Http\Requests\AiAccount\StoreAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountRequest;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiAccountCostSummaryBuilder;
use App\Services\AiAccount\AiAccountFromProposalCreator;
use App\Services\AiAccount\AiAccountGrouper;
use App\Services\AiAccount\AiAccountReminderService;
use App\Services\AiAccount\AiAccountStatusSync;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\SystemRole;
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
            ],
            'meta' => [
                'options' => [
                    'group_function' => AiAccountGroupFunction::options(),
                    'cost_unit' => AiAccountCostUnit::options(),
                    'license_types' => config('ai_accounts.license_types', []),
                ],
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

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function show(AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('view', $aiAccount);
        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->grouper->grouped(collect([$aiAccount->fresh()]), null, $request->user())['groups'][0]['accounts'][0] ?? null],
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

        if ($request->user()->role === SystemRole::Admin && ! empty($validated['password'])) {
            $data['login_password'] = $validated['password'];
        }

        $aiAccount->update($data);
        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã lưu thành công.',
        ]);
    }

    public function destroy(AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('delete', $aiAccount);
        $name = $aiAccount->tool_name;
        $aiAccount->delete();

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
        ]);

        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh(), $request->user())],
            'message' => 'Đã gia hạn tài khoản.',
        ]);
    }

    public function triggerReminder(Request $request): JsonResponse
    {
        $this->authorize('triggerReminder', AiAccount::class);

        $count = $this->reminderService->sendDueReminders();

        return response()->json([
            'success' => true,
            'data' => ['sent' => $count],
            'message' => $count > 0
                ? "Đã gửi {$count} nhắc nhở."
                : 'Không có tài khoản nào cần nhắc hôm nay.',
        ]);
    }

    private function loadAndSyncAccounts()
    {
        $accounts = AiAccount::query()->orderBy('tool_name')->get();
        $this->statusSync->syncCollection($accounts);

        return AiAccount::query()->orderBy('tool_name')->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function accountRow(?AiAccount $account, ?\App\Models\SystemAccount $viewer = null): ?array
    {
        if (! $account) {
            return null;
        }

        $grouped = $this->grouper->grouped(collect([$account]), null, $viewer);

        return $grouped['groups'][0]['accounts'][0] ?? null;
    }
}
