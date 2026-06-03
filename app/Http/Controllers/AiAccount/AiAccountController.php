<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\RenewAiAccountRequest;
use App\Http\Requests\AiAccount\StoreAiAccountRequest;
use App\Http\Requests\AiAccount\UpdateAiAccountRequest;
use App\Models\AiAccount;
use App\Models\AiPurchaseProposal;
use App\Services\AiAccount\AiAccountGrouper;
use App\Services\AiAccount\AiAccountReminderService;
use App\Services\AiAccount\AiAccountStatusSync;
use App\Services\AiAccount\AiPurchaseProposalPresenter;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiAccountStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAccountController extends Controller
{
    public function __construct(
        private readonly AiAccountGrouper $grouper,
        private readonly AiAccountStatusSync $statusSync,
        private readonly AiAccountReminderService $reminderService,
        private readonly AiPurchaseProposalPresenter $proposalPresenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAccount::class);

        $accounts = $this->loadAndSyncAccounts();
        $search = $request->query('search');
        $payload = $this->grouper->grouped($accounts, is_string($search) ? $search : null);
        $summary = $this->grouper->summary($accounts);

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

        $summary = $this->grouper->summary($accounts);
        $summary['proposals'] = $this->proposalPresenter->list($proposals);
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
            'data' => ['account' => $this->grouper->grouped(collect([$aiAccount->fresh()]))['groups'][0]['accounts'][0] ?? null],
        ]);
    }

    public function store(StoreAiAccountRequest $request): JsonResponse
    {
        $data = $this->mapValidated($request->validated());
        $data['status'] = $data['status'] ?? AiAccountStatus::Active;
        $account = AiAccount::create($data);
        $this->statusSync->syncAndSave($account);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($account->fresh())],
            'message' => 'Đã lưu thành công.',
        ], 201);
    }

    public function update(UpdateAiAccountRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $data = $this->mapValidated($request->validated(), $aiAccount);
        $aiAccount->update($data);
        $this->statusSync->syncAndSave($aiAccount);

        return response()->json([
            'success' => true,
            'data' => ['account' => $this->accountRow($aiAccount->fresh())],
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
            'data' => ['account' => $this->accountRow($aiAccount->fresh())],
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapValidated(array $validated, ?AiAccount $existing = null): array
    {
        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $validated['status'] = AiAccountStatus::Cancelled;
        } elseif ($existing === null) {
            unset($validated['status']);
        }

        $validated['notify_before_days'] ??= 14;

        if (isset($validated['group_function'])) {
            $validated['group_function'] = AiAccountGroupFunction::from($validated['group_function']);
        }
        if (isset($validated['cost_unit'])) {
            $validated['cost_unit'] = AiAccountCostUnit::from($validated['cost_unit']);
        }
        if (isset($validated['status']) && is_string($validated['status'])) {
            $validated['status'] = AiAccountStatus::from($validated['status']);
        }

        return $validated;
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
    private function accountRow(?AiAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        $grouped = $this->grouper->grouped(collect([$account]));

        return $grouped['groups'][0]['accounts'][0] ?? null;
    }
}
